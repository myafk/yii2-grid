<?php

namespace common\components\grid;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

class ColumnHelper
{

	const TYPE_STRING = 'string';
	const TYPE_AUTO_COMPLETE_RELATED = 'autoCompleteRelated';
	const TYPE_AUTO_COMPLETE_SELF = 'autoCompleteSelf';
	const TYPE_ARRAY = 'array';
	const TYPE_DATE_TIME = 'dateTime';
	const TYPE_BOOLEAN = 'boolean';
	const TYPE_STATUS_ARRAY = 'statusArray';
	const TYPE_IMAGE = 'image';

	public static function columns(array $columns, $options = []) : array
	{
		$options = array_replace([
			'checkboxColumn' => true,
			'actionColumn' => true,
			'settingsHiddenColumns' => []
		], $options);
		$readyColumns = [
			'query' => [],
			'grid' => []
		];
		if ($options['checkboxColumn']) {
			$readyColumns['grid'][] = [
				'class' => 'kartik\grid\CheckboxColumn',
				'headerOptions' => ['class' => 'kartik-sheet-style'],
				'width' => '25px;'
			];
		}
		foreach ($columns as $key => $column) {
			if (is_string($column)) {
				$name = $column;
				$column = [];
			} else {
				$name = ArrayHelper::remove($column, 'attribute', $key);
			}
			$hidden = ArrayHelper::remove($column, 'hidden', false);
			$settingsHidden = ArrayHelper::getValue($options['settingsHiddenColumns'], $name, $hidden);
			if ($settingsHidden)
				continue;
			$type = ArrayHelper::remove($column, 'type', self::TYPE_STRING);
			$gridOptions = ArrayHelper::remove($column, 'gridOptions', []);
			$queryOptions = ArrayHelper::remove($column, 'queryOptions', []);
			$readyColumn = self::column($name, $type, $column, $gridOptions, $queryOptions);
			$readyColumns['query'][$name] = ArrayHelper::getValue($readyColumn, 'query');
			$readyColumns['grid'][] = ArrayHelper::getValue($readyColumn, 'grid');
		}
		if (is_array($options['actionColumn'])) {
			$readyColumns['grid'][] = $options['actionColumn'];
		}
		else if ($options['actionColumn']) {
			$readyColumns['grid'][] = [
				'class' => 'common\components\grid\ActionColumn',
			];
		}
		return $readyColumns;
	}

	public static function column(string $attribute, string $type, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$options = array_replace_recursive([
			'editable' => false,
		], $options);

		$filterOptions = ArrayHelper::getValue($gridOptions, 'filterOptions');
		Html::addCssClass($filterOptions, 'column-' . $attribute);

		$gridDefaultOptions = [
			'class' => DataColumn::className(),
			'attribute' => $attribute,
			'filterOptions' => $filterOptions
		];
		if ($options['editable']) {
			$gridDefaultOptions['editableOptions'] = [
				'placement' => 'bottom bottom-right',
				'ajaxSettings' => [
					'url' => Url::toRoute('fast-add-edit'),
				]
			];
			$gridDefaultOptions['class'] = 'common\components\grid\EditableColumn';
		}
		$gridOptions = array_replace_recursive($gridDefaultOptions, $gridOptions);

		$queryOptions = array_replace_recursive([
			'attribute' => $attribute
		], $queryOptions);
		$column = $type . 'Column';
		if (method_exists(get_class(), $column)) {
			return static::$column($attribute, $options, $gridOptions, $queryOptions);
		} else {
			return false;
		}
	}

	public static function prepare($queryDefault = null, $query = null, $gridDefault = null, $grid = null)
	{
		$options = [];
		if ($query !== null)
			$options['query'] = array_replace_recursive($queryDefault, $query);
		if ($grid !== null)
			$options['grid'] = array_replace_recursive($gridDefault, $grid);
		return $options;
	}

	public static function stringColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		return static::prepare([], $queryOptions, [], $gridOptions);
	}

	public static function imageColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$defaultGridOptions = [
			'format' => 'raw',
			'value' => function ($model) use ($attribute) {
				return Html::a(Html::img('/' . $model->$attribute), '/' . $model->$attribute, ['target' => '_blank', 'data-pjax' => false, 'js-popup' => true, 'class' => 'grid-img']);
			}
		];
		return static::prepare([], $queryOptions, $defaultGridOptions, $gridOptions);
	}

	public static function dateTimeColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$queryOptions = array_replace_recursive([
			'type' => 'dateTimeRange'
		], $queryOptions);
		$gridOptions = array_replace_recursive([
			'format' => 'datetime',
			'filter' => [
				'format' => 'datetimeRange',
			],
		], $gridOptions);
		return static::prepare([], $queryOptions, [], $gridOptions);
	}

	public static function arrayColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$data = ArrayHelper::getValue($options, 'data');
		$gridDefaultOptions = [
			'filter' => $data,
			'value' => function ($model) use ($attribute, $data) {
				return ArrayHelper::getValue($data, $model->$attribute);
			}
		];
		if ($options['editable'])
			$gridDefaultOptions['editableOptions'] = [
				'inputType' => \kartik\editable\Editable::INPUT_DROPDOWN_LIST,
				'data' => $options['data'],
			];
		return static::prepare([], $queryOptions, $gridDefaultOptions, $gridOptions);
	}

	public static function statusArrayColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		if (!isset($options['icons'])) {
			$options['icons'] = [0 => 'fa fa-times text-danger', 10 => 'fa fa-check text-success'];
		}
		return static::booleanColumn($attribute, $options, $gridOptions, $queryOptions);
	}

	public static function booleanColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$options['data'] = ArrayHelper::getValue($options, 'data', [0 => 'Нет', 1 => 'Да']);
		$data = ArrayHelper::getValue($options, 'data');
		$icons = ArrayHelper::getValue($options, 'icons', [0 => 'glyphicon glyphicon-remove text-danger', 1 => 'glyphicon glyphicon-ok text-success']);
		$gridOptions = array_replace_recursive([
			'format' => 'html',
			'value' => function ($model) use ($attribute, $data, $icons) {
				$value = $model->$attribute;
				$text = ArrayHelper::getValue($data, $value);
				$icon = ArrayHelper::getValue($icons, $value);
				if ($icon)
					return Html::tag('span', '', ['class' => $icon, 'title' => $text]);
				else
					return ArrayHelper::getValue($data, $model->$attribute);
			}
		], $gridOptions);
		return static::arrayColumn($attribute, $options, $gridOptions, $queryOptions);
	}

	public static function autoCompleteColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$ajaxUrl = ArrayHelper::getValue($options, 'ajaxUrl');
		$data = ArrayHelper::getValue($options, 'data');
		$gridDefaultOptions = [
			'filter' => [
				'format' => 'autoComplete',
				'options' => [
					'data' => $data,
					'ajax' => $ajaxUrl ? [
						'url' => ArrayHelper::getValue($options, 'ajaxUrl'),
					] : null,
					'minimumInputLength' => ArrayHelper::getValue($options, 'minimumInputLength', $data !== null ? 0 : 1),
					'value' => ArrayHelper::getValue($options, 'valueText'),
				]
			],
			'value' => ArrayHelper::getValue($options, 'value'),
		];
		return static::prepare([], $queryOptions, $gridDefaultOptions, $gridOptions);
	}

	public static function autoCompleteRelatedColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$options = array_replace_recursive([
		], $options);
		$valueText = ArrayHelper::getValue($options, 'valueText');
		//Если не установлен valueText, то пытаемся его сгенерировать сами основываясь на аттрибуте
		//Например country_id -> в country и т.п.
		if (!$valueText) {
			$attributeExp = explode('_', $attribute);
			unset($attributeExp[count($attributeExp) - 1]); //Убираем id
			$valueRelated = implode('_', $attributeExp);
			$valueAttribute = 'title';
			$options['valueText'] = function ($model) use ($valueRelated, $valueAttribute) {
				return ArrayHelper::getValue($model, "$valueRelated.$valueAttribute");
			};
			$options['value'] = function ($model) use ($valueRelated, $valueAttribute) {
				return ArrayHelper::getValue($model, "$valueRelated.$valueAttribute");
			};
		}
		return self::autoCompleteColumn($attribute, $options, $gridOptions, $queryOptions);
	}

	public static function autoCompleteSelfColumn($attribute, $options = [], $gridOptions = [], $queryOptions = [])
	{
		$options = array_replace_recursive([
			'valueText' => function ($model) use ($attribute) {
				return $model->$attribute;
			},
			'value' => function ($model) use ($attribute) {
				return $model->$attribute;
			}
		], $options);
		return self::autoCompleteColumn($attribute, $options, $gridOptions, $queryOptions);
	}

}