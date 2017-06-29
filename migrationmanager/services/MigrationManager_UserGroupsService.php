<?php

namespace Craft;

class MigrationManager_UserGroupsService extends MigrationManager_BaseMigrationService
{
    protected $source = 'userGroup';
    protected $destination = 'userGroups';

    public function exportItem($id, $fullExport)
    {
        $group = craft()->userGroups->getGroupById($id);

        if (!$group) {
            return false;
        }

        $newGroup = [
            'name' => $group->name,
            'handle' => $group->handle
        ];

        if ($fullExport)
        {
            $newGroup['fieldLayout'] = array();
            $newGroup['requiredFields'] = array();
            $fieldLayout = craft()->fields->getLayoutByType('User');

            foreach ($fieldLayout->getTabs() as $tab) {
                $newGroup['fieldLayout'][$tab->name] = array();
                foreach ($tab->getFields() as $tabField) {

                    $newGroup['fieldLayout'][$tab->name][] = craft()->fields->getFieldById($tabField->fieldId)->handle;
                    if ($tabField->required) {
                        $newGroup['requiredFields'][] = craft()->fields->getFieldById($tabField->fieldId)->handle;
                    }
                }
            }
            $newGroup['permissions'] = $this->getGroupPermissionHandles($id);
            $newGroup['settings'] = craft()->systemSettings->getSettings('users');

            if ($newGroup['settings']['defaultGroup'] != null){
                $group = craft()->userGroups->getGroupById($newGroup['settings']['defaultGroup']);
                $newGroup['settings']['defaultGroup'] = $group->handle;
            }

        }
        return $newGroup;
    }

    public function importItem(Array $data)
    {
        $existing = craft()->userGroups->getGroupByHandle($data['handle']);

        if ($existing) {
            $this->mergeUpdates($data, $existing);
        }

        $userGroup = $this->createModel($data);
        $result = craft()->userGroups->saveGroup($userGroup);
        if ($result)
        {
            if (array_key_exists('permissions', $data)) {
                $permissions = $this->getGroupPermissionIds($data['permissions']);
                if (craft()->userPermissions->saveGroupPermissions($userGroup->id, $permissions)){

                } else {
                    $this->addError('Could not save user group permissions');
                }
            }

            if (array_key_exists('settings', $data)){

                if ($data['settings']['defaultGroup'] != null){
                    $group = craft()->userGroups->getGroupByHandle($data['settings']['defaultGroup']);
                    $data['settings']['defaultGroup'] = $group->id;
                }

                if (craft()->systemSettings->saveSettings('users', $data['settings']))
                {

                } else {
                    $this->addError('Could not save user group settings');
                }
            }




        }

        return $result;
    }

    public function createModel(Array $data)
    {
        $userGroup = new UserGroupModel();
        if (array_key_exists('id', $data)){
            $userGroup->id = $data['id'];
        }

        $userGroup->name = $data['name'];
        $userGroup->handle = $data['handle'];

        if (array_key_exists('fieldLayout', $data)) {
            $requiredFields = array();
            if (array_key_exists('requiredFields', $data)) {
                foreach ($data['requiredFields'] as $handle) {
                    $field = craft()->fields->getFieldByHandle($handle);
                    if ($field) {
                        $requiredFields[] = $field->id;
                    }
                }
            }

            $layout = array();
            foreach ($data['fieldLayout'] as $key => $fields) {
                $fieldIds = array();
                foreach ($fields as $field) {
                    $existingField = craft()->fields->getFieldByHandle($field);
                    if ($existingField) {
                        $fieldIds[] = $existingField->id;
                    } else {
                        $this->addError('Missing field: ' . $field . ' can not add to field layout for User Group: ' . $userGroup->handle);
                    }
                }
                $layout[$key] = $fieldIds;
            }

            $fieldLayout = craft()->fields->assembleLayout($layout, $requiredFields);
            $fieldLayout->type = ElementType::User;

            craft()->fields->deleteLayoutsByType(ElementType::User);

            if (craft()->fields->saveLayout($fieldLayout))
            {

            }
            else
            {
                $this->addError(Craft::t('Couldn’t save user fields.'));
            }

        }
        return $userGroup;

    }

    private function mergeUpdates(&$newSource, $source)
    {
        $newSource['id'] = $source->id;
    }

    private function getGroupPermissionIds($permissions)
    {
         foreach($permissions as &$permission)
        {
            //determine if permission references element, get id if it does
            if (preg_match('/(:)/', $permission))
            {
                $permissionParts = explode(":", $permission);
                $element = null;

                if (preg_match('/entries|entrydrafts/', $permissionParts[0]))
                {
                    $element = craft()->sections->getSectionByHandle($permissionParts[1]);
                } elseif (preg_match('/assetsource/', $permissionParts[0])) {
                    $element = MigrationManagerHelper::getAssetSourceByHandle($permissionParts[1]);
                } elseif (preg_match('/categories/', $permissionParts[0])) {
                    $element = craft()->categories->getGroupByHandle($permissionParts[1]);
                }

                if ($element != null) {
                    $permission = $permissionParts[0] . ':' . $element->id;
                }
            }
        }

        return $permissions;
    }

    private function getGroupPermissionHandles($id)
    {
        $permissions = craft()->userPermissions->getPermissionsByGroupId($id);

        foreach($permissions as &$permission)
        {
            //determine if permission references element, get handle if it does
            if (preg_match('/(:\d)/', $permission))
            {
                $permissionParts = explode(":", $permission);
                $element = null;

                if (preg_match('/entries|entrydrafts/', $permissionParts[0]))
                {
                    $element = craft()->sections->getSectionById($permissionParts[1]);
                } elseif (preg_match('/assetsource/', $permissionParts[0])) {
                    $element = craft()->assetSources->getSourceById($permissionParts[1]);
                } elseif (preg_match('/categories/', $permissionParts[0])) {
                    $element = craft()->categories->getGroupById($permissionParts[1]);
                }

                if ($element != null) {
                    $permission = $permissionParts[0] . ':' . $element->handle;
                }
            }
        }

        return $permissions;
    }

}