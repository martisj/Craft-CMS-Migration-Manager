<?php

namespace Craft;

class MigrationManager_GlobalsService extends MigrationManager_BaseMigrationService
{
    protected $source = 'global:settings';
    protected $destination = 'globals:settings';

    public function exportItem($id, $fullExport)
    {
        $source = craft()->globals->getSetById($id);

        if (!$source) {
            return false;
        }

        $newSource = [
            'name' => $source->name,
            'handle' => $source->handle,
            'fieldLayout' => array(),
            'requiredFields' => array()
        ];

        $fieldLayout = $source->getFieldLayout();

        foreach ($fieldLayout->getTabs() as $tab) {
            $newSource['fieldLayout'][$tab->name] = array();
            foreach ($tab->getFields() as $tabField) {

                $newSource['fieldLayout'][$tab->name][] = craft()->fields->getFieldById($tabField->fieldId)->handle;
                if ($tabField->required)
                {
                    $newSource['requiredFields'][] = craft()->fields->getFieldById($tabField->fieldId)->handle;
                }
            }
        }

        return $newSource;
    }


    public function importItem(Array $data)
    {

        $existing = craft()->globals->getSetByHandle($data['handle']);

        if ($existing) {
            $this->mergeUpdates($data, $existing);
        }

        $set = $this->createModel($data);
        $result = craft()->globals->saveSet($set);

        return $result;
    }

    public function createModel(Array $data)
    {
        $source = new GlobalSetModel();
        if (array_key_exists('id', $data)){
            $source->id = $data['id'];
        }

        $source->name = $data['name'];
        $source->handle = $data['handle'];

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
        foreach($data['fieldLayout'] as $key => $fields)
        {
            $fieldIds = array();
            foreach($fields as $field) {
                $existingField = craft()->fields->getFieldByHandle($field);
                if ($existingField) {
                    $fieldIds[] = $existingField->id;
                } else {
                    $this->addError('Missing field: ' . $field . ' can not add to field layout for Global: ' . $source->handle);
                }
            }
            $layout[$key] = $fieldIds;
        }


        $fieldLayout = craft()->fields->assembleLayout($layout, $requiredFields);
        $source->fieldLayout = $fieldLayout;



        return $source;

    }

    private function mergeUpdates(&$newSource, $source)
    {
        $newSource['id'] = $source->id;
    }

}