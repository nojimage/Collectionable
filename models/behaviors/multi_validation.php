<?php

class MultiValidationBehavior extends ModelBehavior {

	var $settings = array();
	var $defaultSettings = array(
		'restore' => true,
		'saveOptionName' => 'validator',
	);

	var $_backupValidate = array();

	function setup($Model, $settings = array()) {

		$this->settings[$Model->alias] = array_merge($this->defaultSettings, (array)$settings);
		return true;

	}

	function restoreValidate($Model) {

		if (isset($this->_backupValidate[$Model->alias])) {
			$Model->validate = $this->_backupValidate[$Model->alias];
			unset($this->_backupValidate[$Model->alias]);
		}

	}

	function afterSave($Model, $created = true, $options = array()) {

		if ($this->settings[$Model->alias]['restore']) {
			$this->restoreValidate($Model);
		}

		return true;

	}

	function useValidationSet($Model, $method, $useBase = true) {

		$validates = array_map('ucfirst', (array)$method);

		$result = array();
		foreach ($validates as $validate) {

			$property = 'validate' . $validate;
			if (!isset($Model->{$property})) {
				trigger_error(sprintf(__d('collectionable', 'Unexpected property name: Model::$%s was not found.', true), $property));
				return false;
			}
			$result = $this->mergeValidationSet($Model, $result, $Model->{$property});

		}

		if ($useBase) {
			$result = $this->mergeValidationSet($Model, $Model->validate, $result);
		}

		$this->_backupValidate[$Model->alias] = $Model->validate;
		$Model->validate = $result;

		return true;

	}

	function mergeValidationSet($Model) {

		$validationSets = func_get_args();
		/* $Model = */ array_shift($validationSets);

		$result = array();
		foreach ($validationSets as $validationSet) {
			foreach ($validationSet as $field => $ruleSet) {
				foreach ($ruleSet as $name => $rules) {
					if (isset($result[$field][$name])) {
						$result[$field][$name] = array_merge($result[$field][$name], $rules);
					} else {
						$result[$field][$name] = $rules;
					}
				}
			}
		}

		return $result;

	}

	function validatesFor($Model, $set, $options = array()) {
		$useBase = true;
		if (is_bool($options)) {
			$useBase = $options;
			$options = array();
		} else {
			if (isset($options['useBase'])) {
				$useBase = $options['useBase'];
				unset($options['useBase']);
			}
			unset($options['useBase']);
		}

		$Model->useValidationSet($set, $useBase);
		return $Model->validates($options);
	}

	function beforeValidate($Model, $options = array()) {
		$optionName = $this->settings[$Model->alias]['saveOptionName'];

		if (isset($options[$optionName])) {
			$base = true;
			if (is_array($options[$optionName]) && is_bool($end = end($options[$optionName]))) {
				$base = $end;
				array_pop($options[$optionName]);
			}
			$Model->useValidationSet($options[$optionName], $base);
			unset($options[$optionName]);
		}

		return true;
	}

}
