<?php

/**
 * @file plugins/themes/default/JournalEditorRestrictionPlugin.inc.php
 *
 * Copyright (c) 2010-2020 openjournaltheme.com
 * Copyright (c) 2010-2020 openjournaltheme team
 * Read this term of use of this theme here : https://openjournaltheme.com/term-of-conditions/
 *
 * Modify, redistribute or make commercial copy of this part or whole of this code is prohibited without written permission from openjournaltheme.com
 * Modified by openjournaltheme.com
 * contact : openjournaltheme@gmail.com
 *
 * @class JournalEditorRestriction
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class JournalEditorRestrictionPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     * Nama file class dan nama folder tidak boleh sama.
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('Dispatcher::dispatch', [$this, 'dispatcherCallback']);
            HookRegistry::register('LoadHandler', [$this, 'loadHandler']);
            HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'setupBackendPage']);
        }
        return $success;
    }

    public function loadHandler($hookName, $args)
    {
        if (!$this->isCurrentUserAreJournalEditorAndNotJournalManager()) return;

        $request = $this->getRequest();
        $router = $request->getRouter();
        $requestedPage = $router->getRequestedPage($request);
        $requestedOp = $router->getRequestedOp($request);
        $requestedArgs = $router->getRequestedArgs($request);
        // Restrict user to access some pages when the menu is removed
        switch ($requestedPage) {
            case 'management':
                $blackListArgs = [
                    'context',
                    'website',
                    'workflow',
                    'distribution',
                    'access'
                ];
                if (($requestedOp == 'settings' && !empty(array_intersect($blackListArgs, $requestedArgs))) || $requestedOp == 'tools') {
                    $request->redirectHome();
                }
                break;
        }
    }

    /**
     * Block access to API and GridHandler
     */
    public function dispatcherCallback($hookName, $request)
    {
        if (!$this->isCurrentUserAreJournalEditorAndNotJournalManager()) return;

        $router = $request->getRouter();
        
        // Block access to some API endpoint
        if($router instanceof APIRouter){
            $blockedApiEntity = [
                'contexts',
                '_payments',
            ];

            if(!in_array($router->getEntity(), $blockedApiEntity)) return;

            $router->handleAuthorizationFailure($request, 'api.403.unauthorized');
        }

        // Block access to some GridHandler
        if($router instanceof PKPComponentRouter){
            $rpcServiceEndpoint =& $router->getRpcServiceEndpoint($request);
            
            // Let the system handle the request if the rpc service endpoint is not callable
            if(!is_callable($rpcServiceEndpoint)) return;

            [$gridHandler, $gridOp] = $rpcServiceEndpoint;

            if(!$gridHandler instanceof GridHandler) return;

            $blockedGridHandlerClass = [
                'SettingsPluginGridHandler',
                'PluginGalleryGridHandler',
                'UserGridHandler',
            ];

            if(!in_array(get_class($gridHandler), $blockedGridHandlerClass)) return;

            http_response_code('403');
            header('Content-Type: application/json');
			echo $router->handleAuthorizationFailure($request, 'api.403.unauthorized')->getString();
            exit();
        }
        
    }

    public function isCurrentUserAreJournalEditorAndNotJournalManager()
    {
        $currentUser = $this->getRequest()->getUser();
        if(!$currentUser) return false;
        
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $currentUserGroups = $userGroupDao->getByUserId($currentUser->getId(), $this->getCurrentContextId());

        $currentUserGroupNameLocaleKeys = collect($currentUserGroups->toArray())->map(function ($userGroup) {
            return $userGroup->getData('nameLocaleKey');
        })->toArray();

        // Make sure the user is not a journal manager
        if(in_array('default.groups.name.manager', $currentUserGroupNameLocaleKeys)) return false;

        return in_array('default.groups.name.editor', $currentUserGroupNameLocaleKeys);
    }



    public function setupBackendPage($hookName, $args)
    {
        if (!$this->isCurrentUserAreJournalEditorAndNotJournalManager()) return;
        $templateMgr = TemplateManager::getManager($this->getRequest());
        $menu = $templateMgr->getState('menu');
        unset($menu['settings']);
        unset($menu['tools']);

        $templateMgr->setState(['menu' => $menu]);
    }


    public function getDisplayName()
    {
        return __('plugins.generic.journalEditorRestriction.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.journalEditorRestriction.description');
    }
}
