<?php
namespace steadfast\recurringorders\controllers\web;

use Craft;
use craft\web\Controller;
use steadfast\recurringorders\misc\NormalizeTrait;
use yii\web\BadRequestHttpException;
use yii\web\Response;

abstract class BaseWebController extends Controller
{

	use NormalizeTrait;

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
	 * @param $returnUrlObject
	 * @param array $jsonParams
	 * @param null $defaultRedirectUrl
	 *
	 * @return Response
	 *
	 * @throws BadRequestHttpException from `redirectToPostedUrl()` if the redirect param was tampered with.
	 */
	protected function returnSuccessResponse($returnUrlObject = null, $jsonParams = [], $defaultRedirectUrl = null)
	{

		if (Craft::$app->request->getAcceptsJson())
		{
			return $this->asJson(['success' => true] + $jsonParams);
		}

		return $this->redirectToPostedUrl($returnUrlObject, $defaultRedirectUrl);

	}

}
