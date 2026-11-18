<?php

namespace app\components;

use Yii;
use yii\base\BootstrapInterface;
use yii\web\Response;

/**
 * Consistent JSON error envelope for the whole API, matching the format the
 * Node.js services also use (see services/node/src/middleware/errorHandler.js)
 * so React/Postman never have to branch on which backend answered.
 *
 *   success: {"success": true, "data": ...}
 *   error:   {"success": false, "error": {"code": 404, "message": "...", "details": null}}
 *
 * Registered via config/web.php's 'bootstrap' list.
 */
class ErrorResponseFormatter implements BootstrapInterface
{
    public function bootstrap($app)
    {
        if (!($app instanceof \yii\web\Application)) {
            return;
        }

        $app->response->on(Response::EVENT_BEFORE_SEND, function ($event) {
            /** @var Response $response */
            $response = $event->sender;
            if ($response->format !== Response::FORMAT_JSON) {
                return;
            }

            $data = $response->data;
            if (is_array($data) && (array_key_exists('success', $data))) {
                return; // already formatted (e.g. manually built envelope)
            }

            if ($response->isSuccessful) {
                $response->data = ['success' => true, 'data' => $data];
            } else {
                $message = is_array($data) && isset($data['message']) ? $data['message'] : 'Terjadi kesalahan.';
                $details = is_array($data) && isset($data['errors']) ? $data['errors'] : null;
                $response->data = [
                    'success' => false,
                    'error' => [
                        'code' => $response->statusCode,
                        'message' => $message,
                        'details' => $details,
                    ],
                ];
            }
        });
    }
}
