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

    public function isCurrentUserAreJournalEditorAndNotJournalManager()
    {
        $templateMgr = TemplateManager::getManager($this->getRequest());
        $currentUser = $templateMgr->get_template_vars('currentUser');
        if(!$currentUser) return false;

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $currentUserGroups = $userGroupDao->getByUserId($currentUser->getId(), $this->getCurrentContextId());

        // Get List user group name use locale en_US only
        $currentUserGroupNames = collect($currentUserGroups->toArray())->map(function ($userGroup) {
            return strtolower($userGroup->getName('en_US'));
        })->toArray();

        return in_array('journal editor', $currentUserGroupNames) && !in_array('journal manager', $currentUserGroupNames);
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
