<?php
namespace App\Controllers;

use App\Services\AnalyticsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Exception;

/**
 * Analytics Matemático — adopción tecnológica y cálculo diferencial.
 */
class AnalyticsController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * GET /api/analytics/dashboard
     * Métricas, series temporales, análisis matemático y datasets para Chart.js.
     */
    public function getDashboard(Request $request, Response $response, array $args): Response
    {
        try {
            if (!$this->isAdminAuthenticated($request)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }

            $service = new AnalyticsService($this->getDatabase());
            $data = $service->getDashboardData();

            return $this->successResponse($response, 'Analytics cargado correctamente', $data);
        } catch (Exception $e) {
            error_log('Error en getDashboard analytics: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al cargar analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/analytics/matematico?t=4
     * Análisis diferencial en un instante t (semanas).
     */
    public function getMatematico(Request $request, Response $response, array $args): Response
    {
        try {
            if (!$this->isAdminAuthenticated($request)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }

            $params = $request->getQueryParams();
            $t = isset($params['t']) ? (float) $params['t'] : 4.0;
            $t = max(0, min(12, $t));
            $tActual = $t;

            $math = new \App\Services\MathematicalAnalysisService();

            return $this->successResponse($response, 'Análisis matemático obtenido', [
                't' => $t,
                't_actual_sistema' => $tActual,
                'analisis' => $math->analyzeAt($t),
                'curva' => $math->generateCurve(0, 12, 40),
                'puntos_criticos' => $math->findCriticalPoints(),
                'puntos_inflexion' => $math->findInflectionPoints(),
                'alertas' => $math->generateAlerts($t),
            ]);
        } catch (Exception $e) {
            error_log('Error en getMatematico: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error en análisis matemático', 500);
        }
    }

    /**
     * GET /api/analytics/prediccion
     */
    public function getPrediccion(Request $request, Response $response, array $args): Response
    {
        try {
            if (!$this->isAdminAuthenticated($request)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }

            $service = new AnalyticsService($this->getDatabase());
            $dashboard = $service->getDashboardData();
            $t = $dashboard['matematico']['t_actual'] ?? 4.0;

            return $this->successResponse($response, 'Predicción generada', [
                'prediccion' => $dashboard['matematico']['prediccion'],
                't_actual' => $t,
            ]);
        } catch (Exception $e) {
            error_log('Error en getPrediccion: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error en predicción', 500);
        }
    }

    /** Solo administradores del panel Analytics (JWT tipo admin). */
    private function isAdminAuthenticated(Request $request): bool
    {
        $token = $this->getBearerToken($request);
        return $token !== null && $this->verifyAdminJwtToken($token) !== null;
    }
}
