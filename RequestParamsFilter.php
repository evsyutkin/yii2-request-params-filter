<?php

namespace evsyutkin\yii\behaviors;

use Yii;
use yii\base\Behavior;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\base\InlineAction;

class RequestParamsFilter extends Behavior
{
	public $actions = [
		'*' => [
			'allowedParams' => ['r', 'page'],
			'filterMethods' => ['get'],
		]
	];

	public function events()
	{
		return [Controller::EVENT_BEFORE_ACTION => 'beforeAction'];
	}

	public function beforeAction($event)
	{
		$action = $event->action;

		if (!$action instanceof InlineAction) {
			return $event->isValid;
		}

		$requestMethod = Yii::$app->getRequest()->getMethod();

		if (array_key_exists($action->id, $this->actions)) {
			$rules = $this->actions[$action->id];
		} elseif (in_array($action->id, $this->actions)) {
			$rules = [];
		} elseif (isset($this->actions['*'])) {
			$rules = $this->actions['*'];
		} else {
			return $event->isValid;
		}

		if (isset($rules['filterMethods'])) {
			if (is_string($rules['filterMethods'])) {
				$rules['filterMethods'] = [$rules['filterMethods']];
			}
		} else {
			$rules['filterMethods'] = ['get'];
		}
		$filterMethods = array_map('strtoupper', $rules['filterMethods']);

		if (in_array($requestMethod, $filterMethods)) {
			$queryParams = Yii::$app->request->queryParams;
			$queryParamNames = array_keys($queryParams);

			$refMethod = new \ReflectionMethod($action->controller->className(), $action->actionMethod);

			$methodParamNames = array_map(function ($param){
				return $param->name;
			}, $refMethod->getParameters());

			$allowedParams = array_merge($methodParamNames, isset($rules['allowedParams']) ? $rules['allowedParams'] : []);

			$diff = array_diff($queryParamNames, $allowedParams);

			if (!empty($diff)) {
				throw new NotFoundHttpException(\Yii::t('yii', 'Page not found.'));
			}
		}

		$event->isValid;
	}
}
