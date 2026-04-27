<?php
require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

class AddEvent
{
    private $api;

    public function __construct()
    {
        $this->api = Configuration::get('ANALYTICS_GA4_ID');
    }

    public function purchase($eventData)
    {
        $measurementId = 'G-09PSJYVMZX';

        $url = "https://www.google-analytics.com/mp/collect?api_secret={$this->api}&measurement_id={$measurementId}";

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($eventData),
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result !== true;
    }
}
