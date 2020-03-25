<?php
namespace topshelfcraft\recurringorders\controllers\web;

use Craft;
use craft\web\Controller;
use topshelfcraft\recurringorders\controllers\ControllerHelpersTrait;
use yii\web\BadRequestHttpException;
use yii\web\Response;

abstract class BaseWebController extends Controller
{

	use ControllerHelpersTrait;

	/**
	 * @param string $errorMessage
	 * @param array $routeParams
	 *
	 * @return null|Response
	 */
	protected function returnErrorResponse($errorMessage, $routeParams = [])
	{

		if (Craft::$app->getRequest()->getAcceptsJson())
		{
			return $this->asErrorJson($errorMessage);
		}

		Craft::$app->getSession()->setError($errorMessage);

		Craft::$app->getUrlManager()->setRouteParams([
				'errorMessage' => $errorMessage,
			] + $routeParams);

		return null;

	}

	/**
	 * @param $returnUrl
	 * @param $returnUrlObject
	 *
	 * @return Response
	 *
	 * @throws BadRequestHttpException from `redirectToPostedUrl()` if the redirect param was tampered with.
	 */
	protected function returnSuccessResponse($returnUrlObject = null)
	{

		if (Craft::$app->getRequest()->getAcceptsJson())
		{
			return $this->asJson(['success' => true]);
		}

		return $this->redirectToPostedUrl($returnUrlObject, Craft::$app->getRequest()->getReferrer());

	}

}
