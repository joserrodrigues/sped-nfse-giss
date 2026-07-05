<?php

namespace NFePHP\NFSeGinfes\Tests;

/**
 * Soap fake class used for development only
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeGinfes
 * @copyright NFePHP Copyright (c) 2020
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Cleiton Perin <cperin20 at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-ginfes for the canonical source repository
 */
use NFePHP\NFSeGinfes\Common\Soap\SoapBase;
use NFePHP\NFSeGinfes\Common\Soap\SoapInterface;
use NFePHP\Common\Exception\SoapException;
use NFePHP\Common\Certificate;
use Psr\Log\LoggerInterface;

class SoapFake extends SoapBase implements SoapInterface
{
    /**
     * Constructor
     * @param Certificate $certificate
     * @param LoggerInterface $logger
     */
    public function __construct(Certificate $certificate = null, LoggerInterface $logger = null)
    {
        parent::__construct($certificate, $logger);
    }
    
    /**
     * Send soap message to url
     * @param string $operation
     * @param string $url
     * @param string $action
     * @param string $envelope
     * @param array $parameters
     * @return string
     */
    public function send(
        $operation,
        $url,
        $action,
        $envelope,
        $parameters
    ) {
				$this->requestHead = implode("\n", $parameters);
				$this->requestBody = $envelope;

				$json = file_get_contents(__DIR__ . '/response_json.json');
				$data = json_decode($json, true);
				$this->responseBody = $data[$operation]['response'];
				return $this->responseBody;
    }
}
