<?php

namespace firstborn\migrationmanager\services;

use firstborn\migrationmanager\MigrationManager;
use firstborn\migrationmanager\events\FieldEvent;
use Craft;
use craft\fields\BaseOptionsField;
use craft\fields\BaseRelationField;

abstract class BaseContentMigration extends BaseMigration
{

    protected function getContent(&$content, $element){
        foreach ($element->getFieldLayout()->getFields() as $fieldModel) {
            $this->getFieldContent($content['fields'], $fieldModel, $element);
        }
    }

    protected function getFieldContent(&$content, $fieldModel, $parent)
    {
        $field = $fieldModel;//->getField();
        $value = $parent->getFieldValue($field->handle);

        // Fire an 'onExportField' event
        /*$event = new FieldEvent(array(
            'field' => $field,
            'value' => $newField
        ));*/

        // Fire an 'onExportField' event
        $event = new FieldEvent(array(
            'field' => $field,
            'parent' => $parent,
            'value' => $value
        ));

        $this->onExportFieldContent($event);

        if ($event->isValid == false) {
            $value = $event->value;
        } else {
            switch ($field->className()) {
                case 'RichText':
                    if ($value){
                        $value = $value->getRawContent();
                    } else {
                        $value = '';
                    }

                    break;
                case 'Matrix':
                    $model = $parent[$field->handle];
                    $value = $this->getIteratorValues($model, function ($item) {
                        $itemType = $item->getType();
                        $value = [
                            'type' => $itemType->handle,
                            'enabled' => $item->enabled,
                            'fields' => []
                        ];

                        return $value;
                    });
                    break;
                case 'Neo':
                    $model = $parent[$field->handle];
                    $value = $this->getIteratorValues($model, function ($item) {
                        $itemType = $item->getType();
                        $value = [
                            'type' => $itemType->handle,
                            'enabled' => $item->enabled,
                            'modified' => $item->enabled,
                            'collapsed' => $item->collapsed,
                            'level' => $item->level,
                            'fields' => []
                        ];

                        return $value;
                    });
                    break;
                case 'SuperTable':
                    $model = $parent[$field->handle];

                    if ($field->settings['staticField'] == 1){
                        $value = [
                            'new1' => [
                                'type' => $model->typeId,
                                'fields' => []
                            ]
                        ];
                        $this->getContent($value['new1']['fields'], $model);
                    } else {

                        $value = $this->getIteratorValues($model, function ($item) {
                            $value = [
                                'type' => $item->typeId,
                                'fields' => []
                            ];
                            return $value;
                        });
                    }
                    break;
                case 'Dropdown':
                    $value = $value->value;
                    break;
                default:
                    if ($field instanceof BaseRelationField) {
                        $this->getSourceHandles($value);
                    } elseif ($field instanceof BaseOptionsField){
                        $this->getSelectedOptions($value);
                    }
                    break;
            }
        }

        $content[$field->handle] = $value;
    }

    protected function validateImportValues(&$values)
    {
        foreach ($values as $key => &$value) {
            $this->validateFieldValue($values, $key, $value);
        }
    }

    protected function validateFieldValue($parent, $fieldHandle, &$fieldValue)
    {
        $field = Craft::$app->fields->getFieldByHandle($fieldHandle);

        if ($field) {
            // Fire an 'onImportFieldContent' event
            $event = new FieldEvent(array(
                'field' => $field,
                'parent' => $parent,
                'value' => &$fieldValue
            ));

            $this->onImportFieldContent($event);

            if ($event->isValid == false) {
                $fieldValue = $event->value;

            } else {
                switch ($field->className()) {
                    case 'Matrix':
                        foreach($fieldValue as $key => &$matrixBlock){
                            $blockType = MigrationManagerHelper::getMatrixBlockType($matrixBlock['type'], $field->id);
                            if ($blockType) {
                                $blockFields = Craft::$app->fields->getAllFields(null, 'matrixBlockType:' . $blockType->id);
                                foreach($blockFields as &$blockField){
                                    if ($blockField->className() == 'SuperTable') {
                                        $matrixBlockFieldValue = &$matrixBlock['fields'][$blockField->handle];
                                        $this->updateSupertableFieldValue($matrixBlockFieldValue, $blockField);
                                    }
                                }
                            }
                        }
                        break;
                    case 'Neo':
                        foreach($fieldValue as $key => &$neoBlock){
                            $blockType = MigrationManagerHelper::getNeoBlockType($neoBlock['type'], $field->id);
                            if ($blockType) {
                                $blockTabs = $blockType->getFieldLayout()->getTabs();
                                foreach($blockTabs as $blockTab){
                                    $blockFields = $blockTab->getFields();
                                    foreach($blockFields as &$blockTabField){
                                        $neoBlockField = Craft::$app->fields->getFieldById($blockTabField->fieldId);
                                        if ($neoBlockField->className() == 'SuperTable') {
                                            $neoBlockFieldValue = &$neoBlock['fields'][$neoBlockField->handle];
                                            $this->updateSupertableFieldValue($neoBlockFieldValue, $neoBlockField);
                                        }
                                    }
                                }
                            }
                        }

                        break;
                    case 'SuperTable':
                        $this->updateSupertableFieldValue($fieldValue, $field);
                        break;
                }
            }
        }

    }

    protected function updateSupertableFieldValue(&$fieldValue, $field){
        $blockType = Craft::$app->superTable->getBlockTypesByFieldId($field->id)[0];
        foreach ($fieldValue as $key => &$value) {
            $value['type'] = $blockType->id;
        }
    }

    protected function getIteratorValues($element, $settingsFunc)
    {
        $items = $element->getIterator();
        $value = [];
        $i = 1;

        foreach ($items as $item) {
            $itemType = $item->getType();
            $itemFields = $itemType->getFieldLayout()->getFields();
            $itemValue = $settingsFunc($item);
            $fields = [];

            foreach ($itemFields as $field) {
                $this->getFieldContent($fields, $field, $item);
            }

            $itemValue['fields'] = $fields;
            $value['new' . $i] = $itemValue;
            $i++;
        }
        return $value;
    }

    protected function getEntryType($handle, $sectionId)
    {
        $entryTypes = Craft::$app->sections->getEntryTypesBySectionId($sectionId);
        foreach($entryTypes as $entryType)
        {
            if ($entryType->handle == $handle){
                return $entryType;
            }

        }

        return false;
    }

    protected function getSourceHandles(&$value)
    {
        $elements = $value->elements();
        $value = [];
        if ($elements) {
            foreach ($elements as $element) {

                switch ($element->getElementType()) {
                    case 'Asset':
                        $item = [
                            'elementType' => 'Asset',
                            'filename' => $element->filename,
                            'folder' => $element->getFolder()->name,
                            'source' => $element->getSource()->handle
                        ];
                        break;
                    case 'Category':
                        $item = [
                            'elementType' => 'Category',
                            'slug' => $element->slug,
                            'category' => $element->getGroup()->handle
                        ];
                        break;
                    case 'Entry':
                        $item = [
                            'elementType' => 'Entry',
                            'slug' => $element->slug,
                            'section' => $element->getSection()->handle
                        ];
                        break;
                    case 'Tag':
                        $tagValue = [];
                        $this->getContent($tagValue, $element);
                        $item = [
                            'elementType' => 'Tag',
                            'slug' => $element->slug,
                            'group' => $element->getGroup()->handle,
                            'value' => $tagValue
                        ];
                        break;
                    case 'User':
                        $item = [
                            'elementType' => 'User',
                            'username' => $element->username
                        ];
                        break;
                    default:
                        $item = null;
                }

                if ($item)
                {
                    $value[] = $item;
                }


            }
        }

        return $value;
    }

    protected function getSourceIds(&$value)
    {
        if (is_array($value))
        {
            if (is_array($value)) {
                $this->populateIds($value);
            } else {
                $this->getSourceIds($value);
            }
        }
        return;
    }

    protected function getSelectedOptions(&$value){
        $options = $value->getOptions();
        $value = [];
        foreach($options as $option){
            if ($option->selected)
            {
                $value[] = $option->value;
            }
        }
        return $value;

    }

    protected function populateIds(&$value)
    {
        $isElementField = true;
        $ids = [];
        foreach ($value as &$element) {
            if (is_array($element) && key_exists('elementType', $element)) {
                $func = null;
                switch ($element['elementType']) {
                    case 'Asset':
                        $func = '\Craft\MigrationManagerHelper::getAssetByHandle';
                        break;
                    case 'Category':
                        $func = '\Craft\MigrationManagerHelper::getCategoryByHandle';
                        break;
                    case 'Entry':
                        $func = '\Craft\MigrationManagerHelper::getEntryByHandle';
                        break;
                    case 'Tag':
                        $func = '\Craft\MigrationManagerHelper::getTagByHandle';
                        break;
                    case 'User':
                        $func = '\Craft\MigrationManagerHelper::getUserByHandle';
                        break;
                    default:
                        break;
                }

                if ($func){
                    $item = $func( $element );
                    if ($item)
                    {
                        $ids[] = $item->id;
                    }
                }
            } else {
                $isElementField = false;
                $this->getSourceIds($element);
            }
        }

        if ($isElementField){
            $value = $ids;
        }

        return true;
    }

    protected function localizeData(BaseElementModel $element, Array &$data)
    {
        //look for matrix/supertables/neo that are not localized and update the keys to ensure the locale values on child elements remain intact
        $fieldLayout = $element->getFieldLayout();

        foreach ($fieldLayout->getTabs() as $tab) {
            foreach ($tab->getFields() as $tabField) {
                $field = craft()->fields->getFieldById($tabField->fieldId);
                $fieldValue = $element[$field->handle];
                if ($field->translatable == false) {
                    if ( in_array ($field->type , ['Matrix', 'SuperTable', 'Neo']) ) {
                        if ($field->type == 'SuperTable' && $field->settings['staticField'] == 1){
                            $data[$field->handle][$fieldValue->id] = $data[$field->handle]['new1'];
                        } else {
                            $items = $fieldValue->getIterator();
                            $i = 1;
                            foreach ($items as $item) {
                                $data[$field->handle][$item->id] = $data[$field->handle]['new' . $i];
                                unset($data[$field->handle]['new' . $i]);
                                $i++;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Fires an 'onExportFieldContent' event. Event handlers can prevent the default field handling by setting $event->performAction to false.
     *
     * @param Event $event
     *          $event->params['field'] - field
     *          $event->params['parent'] - field parent
     *          $event->params['value'] - current field value, change this value in the event handler to output a different value
     *
     * @return null
     */
    public function onExportFieldContent(FieldEvent $event)
    {
        //route this through fields service for simplified event listening
        $plugin = MigrationManager::getInstance();
        $plugin->fields->onExportFieldContent($event);
    }

    /**
     * Fires an 'onImportFieldContent' event. Event handlers can prevent the default field handling by setting $event->performAction to false.
     *
     * @param Event $event
     *          $event->params['field'] - field
     *          $event->params['parent'] - field parent
     *          $event->params['value'] - current field value, change this value in the event handler to import a different value
     *
     * @return null
     */
    public function onImportFieldContent(FieldEvent $event)
    {
        //route this through fields service for simplified event listening
        $plugin = MigrationManager::getInstance();
        $plugin->fields->onImportFieldContent($event);
    }




}