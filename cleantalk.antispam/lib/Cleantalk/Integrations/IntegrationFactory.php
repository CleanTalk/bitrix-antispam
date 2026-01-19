<?php
namespace Cleantalk\Integrations;

class IntegrationFactory {
    /**
     * List of supported integrations
     */
    protected static $integrations = [
        'Bitrix24Integration',
    ];

    /**
     * Handle integration request
     * @param mixed $fields
     */
    public static function handle($fields) {
        $integrationName = $fields['integration'] ?? null;
        if (!$integrationName) {
            return ['error' => 'No integration specified'];
        }
        if (!in_array($integrationName, self::$integrations, true)) {
            return ['error' => 'Integration not supported'];
        }
        $class = __NAMESPACE__ . "\\$integrationName";
        if (!class_exists($class)) {
            return ['error' => 'Integration class not found'];
        }
        $integration = new $class();
        if (!method_exists($integration, 'process')) {
            return ['error' => 'Integration missing process() method'];
        }
        return $integration->process($fields);
    }
}
