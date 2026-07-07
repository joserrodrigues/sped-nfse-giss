<?php

namespace NFePHP\NFSeGiss;

class ConfigInfo
{

	private $cnpj;
	private $im;
	private $cmun;
	private $razaoSocial;
	private $tpamb;

	private $caminhoCertificado;
	private $senhaCertificado;

	public function __construct(string $cnpj, string $im, string $cmun, string $razaoSocial, int $tpamb, string $caminhoCertificado, string $senhaCertificado)
	{

		if (empty($cnpj)) {
			throw new \Exception('CNPJ não informado');
		}
		if (empty($im)) {
			throw new \Exception('IM não informado');
		}
		if (empty($cmun)) {
			throw new \Exception('Código do Município não informado');
		}
		if (empty($razaoSocial)) {
			throw new \Exception('Razão Social não informada');
		}
		if (empty($tpamb) || !in_array($tpamb, [1, 2])) {
			throw new \Exception('Tipo de Ambiente não informado ou inválido');
		}
		if (empty($caminhoCertificado)) {
			throw new \Exception('Caminho do Certificado não informado');
		}
		if (empty($senhaCertificado)) {
			throw new \Exception('Senha do Certificado não informada');
		}
		$this->cnpj = $cnpj;
		$this->im = $im;
		$this->cmun = $cmun;
		$this->razaoSocial = $razaoSocial;
		$this->tpamb = $tpamb;
		$this->caminhoCertificado = $caminhoCertificado;
		$this->senhaCertificado = $senhaCertificado;
	}

	public function getCnpj()
	{
		return $this->cnpj;
	}

	public function getIm()
	{
		return $this->im;
	}

	public function getCmun()
	{
		return $this->cmun;
	}

	public function getRazaoSocial()
	{
		return $this->razaoSocial;
	}

	public function getTpamb()
	{
		return $this->tpamb;
	}

	public function getCaminhoCertificado()
	{
		return $this->caminhoCertificado;
	}

	public function getSenhaCertificado()
	{
		return $this->senhaCertificado;
	}
}
