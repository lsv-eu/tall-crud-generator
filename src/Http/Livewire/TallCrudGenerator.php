<?php

namespace Ascsoftw\TallCrudGenerator\Http\Livewire;

use Exception;
use Livewire\Component;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;

class TallCrudGenerator extends Component
{
    use WithFeatures;
    use WithHelpers;
    use WithBaseHtml;
    use WithViewCode;
    use WithComponentCode;
    use WithTemplates;

    public $exitCode;
    public $isComplete = false;
    public $generatedCode;
    public $modelPath = '';
    public $isValidModel = false;
    public $modelProps = [];

    public $fields = [];
    public $attributeKey;
    public $confirmingAttributes = false;
    public $attributes = [
        'rules' => '',
        'type' => 'input',
        'options' => ''
    ];

    public $componentName = '';
    public $componentProps = [
        'create_add_modal' => true,
        'create_edit_modal' => true,
        'create_delete_button' => true,
    ];

    public $primaryKeyProps = [
        'in_list' => true,
        'label' => '',
        'sortable' => true,
    ];

    public $advancedSettings = [
        'title' => '',
        'text' => [
            'add_link' => 'Create New',
            'edit_link' => 'Edit',
            'delete_link' => 'Delete',
            'create_button' => 'Save',
            'edit_button' => 'Save',
            'cancel_button' => 'Cancel',
            'delete_button' => 'Delete',
        ],
    ];

    public $showAdvanced = false;

    protected $rules = [
        'modelPath' => 'required',
        'componentName' => 'required|alpha_dash|min:3',
    ];

    protected $messages = [
        'modelPath.required' => 'Please enter Path to your Model',
        'componentName.required' => 'Please enter the name of your component',
        'componentName.alpha_dash' => 'Only alphanumeric, dashes and underscore are allowed',
        'componentName.min' => 'Must be minimum of 3 characters',
    ];

    public function render()
    {
        return view('tall-crud-generator::livewire.tall-crud-generator');
    }

    public function checkModel()
    {
        $this->validateOnly('modelPath');

        //check class exists
        $this->resetValidation();
        if (!class_exists($this->modelPath)) {
            $this->addError('modelPath', 'File does not exists');
            return;
        }

        try {
            $model = new $this->modelPath();
            $this->modelProps['table_name'] = $model->getTable();
            $this->modelProps['primary_key'] = $model->getKeyName();
            $this->modelProps['columns'] = $this->_getColumns(Schema::getColumnListing($model->getTable()), $this->modelProps['primary_key']);
        } catch (Exception $e) {
            $this->addError('modelPath', 'Not a Valid Model Class.');
            return;
        }

        $this->isValidModel = true;
        $this->advancedSettings['title'] = Str::title($this->modelProps['table_name']);
    }

    public function addField()
    {
        $this->fields[] = [
            'column' => '',
            'label' => '',
            'sortable' => false,
            'searchable' => false,
            'in_list' => true,
            'in_add' => true,
            'in_edit' => true,
            'attributes' => [
                'rules' => '',
                'type' => 'input',
                'options' => '{"1" : "Yes", "0": "No"}'
            ],
        ];
        $this->resetValidation('fields');
    }

    public function deleteField($i)
    {
        unset($this->fields[$i]);
        $this->fields = array_values($this->fields);
        $this->resetValidation('fields');
    }

    public function showAttributes($i)
    {
        $this->confirmingAttributes = true;
        $this->attributes = $this->fields[$i]['attributes'];
        $this->attributeKey = $i;
    }

    public function addRule($rule)
    {
        $this->attributes['rules'] .= $rule . ',';
    }

    public function clearRules()
    {
        $this->attributes['rules'] = '';
    }

    public function setAttributes()
    {
        $this->fields[$this->attributeKey]['attributes'] = $this->attributes;
        $this->confirmingAttributes = false;
        $this->attributeKey = false;
    }

    public function validateSettings()
    {
        $this->isComplete = false;
        $this->resetValidation('fields');

        if (empty($this->fields)) {
            $this->addError('fields', 'At least 1 Field should be added.');
            return;
        }

        if (!$this->_validateEmptyColumns()) {
            $this->addError('fields', 'Please select column for all fields.');
            return;
        }

        if (!$this->_validateUniqueFields()) {
            $this->addError('fields', 'Please do not select a column more than once.');
            return;
        }

        if (!$this->_validateEachRow()) {
            return;
        }

        if (!$this->_validateDisplayColumn()) {
            $this->addError('fields', 'Please select at least 1 Field to Display in Listing Column.');
            return;
        }

        if (!$this->_validateCreateColumn()) {
            $this->addError('fields', 'Please select at least 1 Field to Display in Create Column.');
            return;
        }

        if (!$this->_validateEditColumn()) {
            $this->addError('fields', 'Please select at least 1 Field to Display in Edit Column.');
            return;
        }

        $this->validateOnly('componentName');

        $this->_generateFiles();
    }

    private function _generateFiles()
    {
        $code = $this->_generateComponentCode();
        $html = $this->_generateViewHtml();
        $props = [
            'modelPath' => $this->modelPath,
            'model' => $this->_getModelName(),
            'modelProps' => $this->modelProps,
            'fields' => $this->fields,
            'componentProps' => $this->componentProps,
            'primaryKeyProps' => $this->primaryKeyProps,
            'advancedSettings' => $this->advancedSettings,
            'html' => $html,
            'code' => $code,
        ];

        $this->exitCode = Artisan::call('livewire:tall-crud-generator', [
            'name' => $this->componentName,
            'props' => $props,
            '--child' => false,
        ]);

        if ($this->exitCode == 0) {
            if ($this->_isAddFeatureEnabled() || $this->_isEditFeatureEnabled() || $this->_isDeleteFeatureEnabled()) {
                $this->exitCode = Artisan::call('livewire:tall-crud-generator', [
                    'name' => $this->componentName . 'Child',
                    'props' => $props,
                    '--child' => true,
                ]);
            }
        }

        $this->generatedCode = "@livewire('" . $this->componentName . "')";
        $this->isComplete = true;
    }
}