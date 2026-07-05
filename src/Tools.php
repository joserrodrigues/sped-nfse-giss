<?php

namespace NFePHP\NFSeGinfes;

/**
 * Class for comunications with NFSe webserver in Ginfes Standard
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

use NFePHP\Common\Certificate;
use NFePHP\Common\Validator;
use NFePHP\NFSeGinfes\Common\Signer;
use NFePHP\NFSeGinfes\Common\Tools as BaseTools;

class Tools extends BaseTools
{
    const ERRO_EMISSAO = 1;
    const SERVICO_NAO_CONCLUIDO = 2;


    protected $xsdpath;

    public function __construct($config, Certificate $cert)
    {
        parent::__construct($config, $cert);
        $path = realpath(
            __DIR__ . '/../storage/schemes'
        );
        $this->xsdpath = $path;
    }

    /**
     * Envia LOTE de RPS para emissão de NFSe (ASSINCRONO)
     * @param array $arps Array contendo de 1 a 50 RPS::class
     * @param string $lote Número do lote de envio
     * @return string
     * @throws \Exception
     */
    public function recepcionarLoteRps(array $arps, string $lote)
    {
        $operation = 'RecepcionarLoteRps';
        $no_of_rps_in_lot = count($arps);
        if ($no_of_rps_in_lot > 50) {
            throw new \Exception('O limite é de 50 RPS por lote enviado.');
        }
        $content = '';
        foreach ($arps as $rps) {
            $rps->config($this->config);
            $content .= $rps->render();
        }

				$contentmsg = "<EnviarLoteRpsEnvio xmlns=\"http://www.giss.com.br/enviar-lote-rps-envio-v2_04.xsd\""
						. " xmlns:tipos=\"http://www.giss.com.br/tipos-v2_04.xsd\">"
						. "<LoteRps Id=\"$lote\" versao=\"2.04\">"
						. "<tipos:NumeroLote>$lote</tipos:NumeroLote>"
						. "<tipos:Prestador>"
								. "<tipos:CpfCnpj>"
										. "<tipos:Cnpj>" . $this->config->cnpj . "</tipos:Cnpj>"
								. "</tipos:CpfCnpj>"
								. "<tipos:InscricaoMunicipal>" . $this->config->im . "</tipos:InscricaoMunicipal>"
						. "</tipos:Prestador>"
						. "<tipos:QuantidadeRps>$no_of_rps_in_lot</tipos:QuantidadeRps>"
						. "<tipos:ListaRps>"
						. $content
						. "</tipos:ListaRps>"
						. "</LoteRps>"
						. "</EnviarLoteRpsEnvio>";


				$contentmsg = $this->signAllRps204InLote($contentmsg);

        $content = Signer::sign(
            $this->certificate,
            $contentmsg,
            'LoteRps',
            'Id',
            OPENSSL_ALGO_SHA1,
            [false, false, null, null],
            'EnviarLoteRpsEnvio'
        );
        $content = str_replace(['<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>'], '', $content);
				Validator::isValid($contentmsg, $this->xsdpath . "/enviar-lote-rps-envio-v2_04.xsd");
        return $this->send($content, $operation);
    }

    /**
     * Sign all RPS nodes inside lote XML for ABRASF 2.04 / GISS RTC
     * @param string $contentmsg
     * @return string
     */
    protected function signAllRps204InLote($contentmsg)
    {
        $nodeIndex = 0;

        while ($this->countUnsignedRps204($contentmsg) > 0) {
            $contentmsg = Signer::sign(
                $this->certificate,
                $contentmsg,
                'InfDeclaracaoPrestacaoServico',
                'Id',
                OPENSSL_ALGO_SHA1,
                [false, false, null, null],
                'Rps',
                $nodeIndex
            );
            $contentmsg = str_replace(
                ['<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>'],
                '',
                $contentmsg
            );
            $nodeIndex++;
        }

        return $contentmsg;
    }

    protected function countUnsignedRps204($contentmsg)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($contentmsg);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('tipos', 'http://www.giss.com.br/tipos-v2_04.xsd');
        $xpath->registerNamespace('dsig', 'http://www.w3.org/2000/09/xmldsig#');

        return $xpath->query(
            '//tipos:Rps[tipos:InfDeclaracaoPrestacaoServico[@Id] and not(dsig:Signature)]'
        )->length;
    }

    /**
     * Consulta Lote RPS (SINCRONO) após envio com recepcionarLoteRps() (ASSINCRONO)
     * complemento do processo de envio assincono.
     * Que deve ser usado quando temos mais de um RPS sendo enviado
     * por vez.
     * @param string $protocolo
     * @return string
     */
    public function consultarLoteRps($protocolo)
    {

				$operation = 'ConsultarLoteRps';
				$content = "<ConsultarLoteRpsEnvio "
						. "xmlns=\"http://www.giss.com.br/consultar-lote-rps-envio-v2_04.xsd\" "
						. "xmlns:tipos=\"http://www.giss.com.br/tipos-v2_04.xsd\">"
						. $this->buildPrestador204()
						. "<Protocolo>$protocolo</Protocolo>"
						. "</ConsultarLoteRpsEnvio>";
				$xsd = '/consultar-lote-rps-envio-v2_04.xsd';

        //assinatura dos dados
        $content = Signer::sign(
            $this->certificate,
            $content,
            'ConsultarLoteRpsEnvio',
            '',
            OPENSSL_ALGO_SHA1,
            [false, false, null, null]
        );
        $content = str_replace(['<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>'], '', $content);
        Validator::isValid($content, $this->xsdpath . $xsd);
        return $this->send($content, $operation);
    }

    /**
     * Consulta NFSe emitidas em um periodo e por tomador (SINCRONO)
     * @param string $dini
     * @param string $dfim
     * @param string $tomadorCnpj
     * @param string $tomadorCpf
     * @param string $tomadorIM
     * @return string
     */
    public function consultarNfse($dini, $dfim, $tomadorCnpj = null, $tomadorCpf = null, $tomadorIM = null)
    {
				$operation = 'ConsultarNfseServicoPrestado';
				$content = "<ConsultarNfseServicoPrestadoEnvio "
					. "xmlns=\"http://www.giss.com.br/consultar-nfse-servico-prestado-envio-v2_04.xsd\" "
					. "xmlns:tipos=\"http://www.giss.com.br/tipos-v2_04.xsd\">"
					. $this->buildPrestador204()
					. "<PeriodoEmissao>"
					. "<DataInicial>$dini</DataInicial>"
					. "<DataFinal>$dfim</DataFinal>"
					. "</PeriodoEmissao>"
					. $this->buildTomador204($tomadorCnpj, $tomadorCpf, $tomadorIM)
					. "<Pagina>1</Pagina>"
					. "</ConsultarNfseServicoPrestadoEnvio>";
				$signTag = 'ConsultarNfseServicoPrestadoEnvio';
				$xsd = '/consultar-nfse-servico-prestado-envio-v2_04.xsd';       

        //assinatura dos dados
        $content = Signer::sign(
            $this->certificate,
            $content,
            $signTag,
            '',
            OPENSSL_ALGO_SHA1,
            [false, false, null, null]
        );
        $content = str_replace(['<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>'], '', $content);
        Validator::isValid($content, $this->xsdpath . $xsd);
        return $this->send($content, $operation);
    }

    /**
     * Consulta NFSe por RPS (SINCRONO)
     * @param integer $numero
     * @param string $serie
     * @param integer $tipo
     * @return string
     */
    public function consultarNfsePorRps($numero, $serie, $tipo)
    {
        $operation = 'ConsultarNfsePorRps';
        $content = "<ConsultarNfseRpsEnvio "
            . "xmlns=\"http://www.giss.com.br/consultar-nfse-rps-envio-v2_04.xsd\" "
            . "xmlns:tipos=\"http://www.giss.com.br/tipos-v2_04.xsd\">"
            . "<IdentificacaoRps>"
            . "<tipos:Numero>$numero</tipos:Numero>"
            . "<tipos:Serie>$serie</tipos:Serie>"
            . "<tipos:Tipo>$tipo</tipos:Tipo>"
            . "</IdentificacaoRps>"
            . $this->buildPrestador204()
            . "</ConsultarNfseRpsEnvio>";

        $content = Signer::sign(
            $this->certificate,
            $content,
            'ConsultarNfseRpsEnvio',
            '',
            OPENSSL_ALGO_SHA1,
            [false, false, null, null]
        );
        $content = str_replace(['<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>'], '', $content);
        Validator::isValid($content, $this->xsdpath . '/consultar-nfse-rps-envio-v2_04.xsd');
        return $this->send($content, $operation);
    }

    /**
     * Solicita o cancelamento de NFSe (SINCRONO)
     * @param integer $numero
     * @param integer $codigo
     * @param string $id
     * @param string $versao
     * @return string
     */
    public function cancelarNfse($numero, $codigo = self::ERRO_EMISSAO, $id = null, $versao = "2")
    {
        if (empty($id)) {
            $id = $numero;
        }

        $operation = 'CancelarNfse';
        $xml = "<CancelarNfseEnvio "
            . "xmlns=\"http://www.giss.com.br/cancelar-nfse-envio-v2_04.xsd\" "
            . "xmlns:tipos=\"http://www.giss.com.br/tipos-v2_04.xsd\">"
            . "<Pedido>"
            . "<tipos:InfPedidoCancelamento Id=\"$id\">"
            . "<tipos:IdentificacaoNfse>"
            . "<tipos:Numero>$numero</tipos:Numero>"
            . "<tipos:CpfCnpj>"
            . "<tipos:Cnpj>" . $this->config->cnpj . "</tipos:Cnpj>"
            . "</tipos:CpfCnpj>"
            . "<tipos:InscricaoMunicipal>" . $this->config->im . "</tipos:InscricaoMunicipal>"
            . "<tipos:CodigoMunicipio>" . $this->config->cmun . "</tipos:CodigoMunicipio>"
            . "</tipos:IdentificacaoNfse>"
            . "<tipos:CodigoCancelamento>$codigo</tipos:CodigoCancelamento>"
            . "</tipos:InfPedidoCancelamento>"
            . "</Pedido>"
            . "</CancelarNfseEnvio>";

        $content = Signer::sign(
            $this->certificate,
            $xml,
            'InfPedidoCancelamento',
            'Id',
            OPENSSL_ALGO_SHA1,
            [false, false, null, null],
            'Pedido'
        );
        $content = str_replace(['<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>'], '', $content);
        Validator::isValid($content, $this->xsdpath . '/cancelar-nfse-envio-v2_04.xsd');
        return $this->send($content, $operation);
    }

    protected function buildPrestador204()
    {
        return "<Prestador>"
            . "<tipos:CpfCnpj>"
            . "<tipos:Cnpj>" . $this->config->cnpj . "</tipos:Cnpj>"
            . "</tipos:CpfCnpj>"
            . "<tipos:InscricaoMunicipal>" . $this->config->im . "</tipos:InscricaoMunicipal>"
            . "</Prestador>";
    }

    protected function buildTomador204($tomadorCnpj = null, $tomadorCpf = null, $tomadorIM = null)
    {
        if (!$tomadorCnpj && !$tomadorCpf) {
            return '';
        }

        $content = "<Tomador><tipos:CpfCnpj>";
        if ($tomadorCnpj) {
            $content .= "<tipos:Cnpj>$tomadorCnpj</tipos:Cnpj>";
        } else {
            $content .= "<tipos:Cpf>$tomadorCpf</tipos:Cpf>";
        }
        $content .= "</tipos:CpfCnpj>";
        if ($tomadorIM) {
            $content .= "<tipos:InscricaoMunicipal>$tomadorIM</tipos:InscricaoMunicipal>";
        }
        $content .= "</Tomador>";

        return $content;
    }
}
