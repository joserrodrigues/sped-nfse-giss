<?php

namespace NFePHP\NFSeGinfes\Tests;

use NFePHP\NFSeGinfes\Rps;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use NFePHP\Common\Certificate;

use NFePHP\NFSeGinfes\Tests\SoapFake;
use NFePHP\NFSeGinfes\Common\Soap\SoapInterface;
use NFePHP\NFSeGinfes\Common\Soap\SoapCurl;
use NFePHP\NFSeGinfes\Tools;

class RpsTest extends TestCase
{
    public $std;
    public $fixturesPath;
    
    public function __construct()
    {
        parent::__construct();       
        $this->fixturesPath = dirname(__FILE__) . '/fixtures/';
    }
    
    public function testCanInstantiate()
    {
				$rps = $this->loadFakeRPS();
        $this->assertInstanceOf('NFePHP\NFSeGinfes\Rps', $rps);
    }

    public function testCannotInstantiateWithoutData()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RPS data is required.');

        new Rps(new \stdClass());
    }
    

		public function testRecepcionarLoteRps(){
				
				$rps = $this->loadFakeRPS();
			
				$tools = $this->createToolsWithFakeSoap();

				$lote = time();
				$response = $tools->recepcionarLoteRps([$rps], $lote);
				$this->assertStringContainsString(
					'RecepcionarLoteRpsRequest xmlns="http://nfse.abrasf.org.br"',
					$tools->lastRequest
				);
				$this->assertStringContainsString('<nfseCabecMsg xmlns="">', $tools->lastRequest);
				$this->assertStringContainsString('<nfseDadosMsg xmlns="">', $tools->lastRequest);
				$this->assertStringContainsString(
					'http://www.giss.com.br/cabecalho-v2_04.xsd',
					$tools->lastRequest
				);
				$this->assertStringNotContainsString(
					'xmlns:ns1="http://ws-homologacao-rtc.giss.com.br"',
					$tools->lastRequest
				);
				$this->assertStringContainsString('InfDeclaracaoPrestacaoServico Id=&quot;rps11&quot;', $tools->lastRequest);
				$this->assertStringContainsString('Signature', $tools->lastRequest);

				$this->assertStringContainsString('EnviarLoteRpsResposta', $response);
				$this->assertStringContainsString('NumeroLote', $response);
				$this->assertStringContainsString('Protocolo', $response);
		}

		public function testConsultarLoteRps()
		{
			$protocolo = '122646';

			$tools = $this->createToolsWithFakeSoap();

			$response = $tools->consultarLoteRps($protocolo);

			$this->assertAbrasf204SoapRequest($tools, 'ConsultarLoteRpsRequest');
			$body = html_entity_decode($tools->lastRequest);
			$this->assertStringContainsString('ConsultarLoteRpsEnvio', $body);
			$this->assertStringContainsString(
				'http://www.giss.com.br/consultar-lote-rps-envio-v2_04.xsd',
				$body
			);
			$this->assertStringContainsString(
				"<Protocolo>$protocolo</Protocolo>",
				$body
			);
			$this->assertStringContainsString('Prestador', $tools->lastRequest);
			$this->assertStringContainsString('InscricaoMunicipal', $tools->lastRequest);

			$this->assertStringContainsString('ConsultarLoteRpsResposta', $response);
			$this->assertStringContainsString('InfNfse', $response);
			$this->assertStringContainsString('DataEmissao', $response);			
			$this->assertStringContainsString('DescricaoCodigoTributacaoMunicipio', $response);			
			$this->assertStringContainsString('InfDeclaracaoPrestacaoServico', $response);			
		}

		public function testConsultarNfse()
		{
			$dini = '2026-01-01';
			$dfim = '2026-07-31';
			$tools = $this->createToolsWithFakeSoap();

			$response = $tools->consultarNfse($dini, $dfim);

			$this->assertAbrasf204SoapRequest($tools, 'ConsultarNfseServicoPrestadoRequest');
			$body = html_entity_decode($tools->lastRequest);
			$this->assertStringContainsString('ConsultarNfseServicoPrestadoEnvio', $body);
			$this->assertStringContainsString("<DataInicial>$dini</DataInicial>", $body);
			$this->assertStringContainsString("<DataFinal>$dfim</DataFinal>", $body);
			$this->assertStringContainsString('<Pagina>1</Pagina>', $body);
			$this->assertStringContainsString('Prestador', $tools->lastRequest);
			$this->assertStringContainsString('PeriodoEmissao', $tools->lastRequest);

		$this->assertStringContainsString('ConsultarNfseServicoPrestadoResposta', $response);
		$this->assertStringContainsString('InfNfse', $response);
		$this->assertStringContainsString('DataEmissao', $response);
		$this->assertStringContainsString('DescricaoCodigoTributacaoMunicipio', $response);
		$this->assertStringContainsString('InfDeclaracaoPrestacaoServico', $response);
	}

		public function testConsultarNfsePorRps()
		{
			$tools = $this->createToolsWithFakeSoap();

			$response = $tools->consultarNfsePorRps(15877, '001', 1);

			$this->assertAbrasf204SoapRequest($tools, 'ConsultarNfsePorRpsRequest');
			$body = html_entity_decode($tools->lastRequest);
			$this->assertStringContainsString('ConsultarNfseRpsEnvio', $body);
			$this->assertStringContainsString(
				'http://www.giss.com.br/consultar-nfse-rps-envio-v2_04.xsd',
				$body
			);
			$this->assertStringContainsString('<tipos:Numero>15877</tipos:Numero>', $body);
			$this->assertStringContainsString('<tipos:Serie>001</tipos:Serie>', $body);
			$this->assertStringContainsString('<tipos:Tipo>1</tipos:Tipo>', $body);
			$this->assertStringContainsString('Prestador', $tools->lastRequest);
			$this->assertStringContainsString('InscricaoMunicipal', $tools->lastRequest);

			$this->assertStringContainsString('ConsultarNfseRpsResposta', $response);
			$this->assertStringContainsString('InfNfse', $response);
			$this->assertStringContainsString('DataEmissao', $response);
			$this->assertStringContainsString('DescricaoCodigoTributacaoMunicipio', $response);
			$this->assertStringContainsString('InfDeclaracaoPrestacaoServico', $response);			
		}

		public function testCancelarNfse()
		{
			$numero = 12345;
			$tools = $this->createToolsWithFakeSoap();

			$response = $tools->cancelarNfse($numero);

			$this->assertAbrasf204SoapRequest($tools, 'CancelarNfseRequest');
			$body = html_entity_decode($tools->lastRequest);
			$this->assertStringContainsString('CancelarNfseEnvio', $body);
			$this->assertStringContainsString(
				'http://www.giss.com.br/cancelar-nfse-envio-v2_04.xsd',
				$body
			);
			$this->assertStringContainsString("<tipos:Numero>$numero</tipos:Numero>", $body);
			$this->assertStringContainsString(
				'<tipos:CodigoCancelamento>1</tipos:CodigoCancelamento>',
				$body
			);
			$this->assertStringContainsString('Cnpj', $tools->lastRequest);
			$this->assertStringContainsString('InscricaoMunicipal', $tools->lastRequest);

			$this->assertStringContainsString('CancelarNfseResposta', $response);
	}

    public function testInterfaceImplementation()
    {
        $class = new ReflectionClass(Rps::class);

        foreach ($class->getInterfaces() as $interface) {
            $interfaceMethods = $interface->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($interfaceMethods as $interfaceMethod) {
                $methodName = $interfaceMethod->getName();
                $parameters = $interfaceMethod->getParameters();

                $classMethod = $class->getMethod($methodName);
                $childParameters = $classMethod->getParameters();

                $failMessage = sprintf(
                    'A assinatura do método %s::%s precisa estar igual na interface %s::%s.',
                    $class->name,
                    $methodName,
                    $interface->name,
                    $methodName
                );

                if (empty($parameters)) {
                    $this->assertEmpty($childParameters, $failMessage);
                }

                foreach ($parameters as $index => $parameter) {
                    $this->assertEquals($parameter->__toString(), $childParameters[$index]->__toString(), $failMessage);
                }
            }
        }
    }

	private function loadFakeRPS(): Rps
	{
		$std = new \stdClass();
		$std->version = '4.00'; //indica qual JsonSchema USAR na validação
		$std->InfDeclaracaoPrestacaoServico = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->Rps = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->Rps->IdentificacaoRps = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->Rps->IdentificacaoRps->Numero = 11; //limite 15 digitos
		$std->InfDeclaracaoPrestacaoServico->Rps->IdentificacaoRps->Serie = '001'; //BH deve ser string numerico
		$std->InfDeclaracaoPrestacaoServico->Rps->IdentificacaoRps->Tipo = 1; //1 - RPS 2-Nota Fiscal Conjugada (Mista) 3-Cupom
		$std->InfDeclaracaoPrestacaoServico->Rps->DataEmissao = '2026-07-01';
		$std->InfDeclaracaoPrestacaoServico->Competencia = '2026-07-01';
		$std->InfDeclaracaoPrestacaoServico->Rps->status = 1;
		$std->InfDeclaracaoPrestacaoServico->Servico = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->Servico->ItemListaServico = '01.01';
		$std->InfDeclaracaoPrestacaoServico->Servico->CodigoTributacaoMunicipio = '620150101';
		$std->InfDeclaracaoPrestacaoServico->Servico->Discriminacao = 'Teste de RPS';
		$std->InfDeclaracaoPrestacaoServico->Servico->CodigoMunicipio = 3547809;
		$std->InfDeclaracaoPrestacaoServico->Servico->CodigoPais = '1058';
		$std->InfDeclaracaoPrestacaoServico->Servico->MunicipioIncidencia = 3547809;
		$std->InfDeclaracaoPrestacaoServico->Servico->ExigibilidadeISS = 1;
		$std->InfDeclaracaoPrestacaoServico->Servico->IssRetido = 1;
		$std->InfDeclaracaoPrestacaoServico->Servico->Valores = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->Servico->Valores->ValorServicos = 100.00;
		$std->InfDeclaracaoPrestacaoServico->Servico->Valores->ValorIss = 0.00;
		$std->InfDeclaracaoPrestacaoServico->Servico->Valores->Aliquota = 10.00;
		$std->InfDeclaracaoPrestacaoServico->Prestador = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->Prestador->CpfCnpj = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->Prestador->CpfCnpj->Cnpj = "81089200000164";
		$std->InfDeclaracaoPrestacaoServico->Prestador->InscricaoMunicipal = "1231331";
		$std->InfDeclaracaoPrestacaoServico->TomadorServico = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->IdentificacaoTomador = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->IdentificacaoTomador->CpfCnpj = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->IdentificacaoTomador->CpfCnpj->Cnpj = "42397920000135";
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->RazaoSocial = "Fulano de Tal";
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco = new \stdClass();
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco->Endereco = 'Rua das Rosas';
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco->Numero = '111';
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco->Complemento = 'Sobre Loja';
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco->Bairro = 'Centro';
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco->CodigoMunicipio = 3106200;
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco->Uf = 'MG';
		$std->InfDeclaracaoPrestacaoServico->TomadorServico->Endereco->Cep = 30160010;
		$std->InfDeclaracaoPrestacaoServico->RegimeEspecialTributacao = 1;
		$std->InfDeclaracaoPrestacaoServico->OptanteSimplesNacional = 1;
		$std->InfDeclaracaoPrestacaoServico->IncentivoFiscal = 2;

		$rps = new Rps($std);
		return $rps;
	}

	private function loadConfig(): string {
		$config = [
			'cnpj' => '81089200000164',
			'im' => '1231331',
			'cmun' => '3547809',
			'razao' => 'Dev Software LTDA',
			'tpamb' => 2
		];

		$configJson = json_encode($config);
		return $configJson;
	}

	private function createToolsWithFakeSoap(): Tools
	{
		//* Fake Connection
		$content = file_get_contents(__DIR__ . '/fixtures/expired_certificate.pfx');
		$cert = Certificate::readPfx($content, 'associacao');

		$tools = new Tools($this->loadConfig(), $cert);

		$soap = new SoapFake();
		$soap->disableCertValidation(true);		
		/*/
		$content = file_get_contents(__DIR__ . '/fixtures/eCNPJ_School.pfx');

		$cert = Certificate::readPfx($content, 'password');

		$tools = new Tools($this->loadConfig(), $cert);

		$soap = new SoapCurl();
		//*/

		$tools->loadSoapClass($soap);

		return $tools;
	}

	private function assertAbrasf204SoapRequest(Tools $tools, string $requestElement): void
	{
		$this->assertStringContainsString(
			"$requestElement xmlns=\"http://nfse.abrasf.org.br\"",
			$tools->lastRequest
		);
		$this->assertStringContainsString('<nfseCabecMsg xmlns="">', $tools->lastRequest);
		$this->assertStringContainsString('<nfseDadosMsg xmlns="">', $tools->lastRequest);
		$this->assertStringContainsString(
			'http://www.giss.com.br/cabecalho-v2_04.xsd',
			$tools->lastRequest
		);
		$this->assertStringContainsString('Signature', $tools->lastRequest);
	}
}
