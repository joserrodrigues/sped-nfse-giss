<?php

namespace NFePHP\NFSeGinfes\Common;

/**
 * Class for RPS XML convertion (ABRASF v4 / GISS v2.04)
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

use DOMElement;
use DOMNode;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

class FactoryV4
{
    /**
     * @var stdClass
     */
    protected $std;

    /**
     * @var stdClass
     */
    protected $inf;

    /**
     * @var Dom
     */
    protected $dom;

    /**
     * @var DOMNode
     */
    protected $rps;

    /**
     * @var \stdClass
     */
    protected $config;

    /**
     * Constructor
     * @param stdClass $std
     */
    public function __construct(stdClass $std)
    {
        $this->std = $std;
        $this->inf = $std->infdeclaracaoprestacaoservico;

        $this->dom = new Dom('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;
        $this->rps = $this->dom->createElement('tipos:Rps');
    }

    /**
     * Add config
     * @param \stdClass $config
     */
    public function addConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Builder, converts stdClass Rps in XML Rps
     * @return string RPS in XML string format
     */
    public function render()
    {
        $infDecl = $this->dom->createElement('tipos:InfDeclaracaoPrestacaoServico');
        $infDecl->setAttribute('Id', $this->getInfDeclaracaoId());

        $this->addInnerRps($infDecl);
        $this->dom->addChild(
            $infDecl,
            'tipos:Competencia',
            $this->inf->competencia,
            true
        );
        $this->addServico($infDecl);
        $this->addPrestador($infDecl);
        $this->addTomadorServico($infDecl);
        $this->addIntermediario($infDecl);
        $this->addConstrucao($infDecl);
        $this->dom->addChild(
            $infDecl,
            'tipos:RegimeEspecialTributacao',
            isset($this->inf->regimeespecialtributacao) ? $this->inf->regimeespecialtributacao : null,
            false
        );
        $this->dom->addChild(
            $infDecl,
            'tipos:OptanteSimplesNacional',
            $this->inf->optantesimplesnacional,
            true
        );
        $this->dom->addChild(
            $infDecl,
            'tipos:IncentivoFiscal',
            $this->inf->incentivofiscal,
            true
        );

        $this->rps->appendChild($infDecl);
        $this->dom->appendChild($this->rps);

        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->dom->saveXML());
    }

    /**
     * Includes inner Rps TAG in InfDeclaracaoPrestacaoServico
     * @param DOMNode $parent
     */
    protected function addInnerRps(DOMNode &$parent)
    {
        if (empty($this->inf->rps)) {
            return;
        }

        $rps = $this->inf->rps;
        $node = $this->dom->createElement('tipos:Rps');

        $this->addIdentificacaoRps($node, $rps);
        $this->dom->addChild(
            $node,
            'tipos:DataEmissao',
            $rps->dataemissao,
            true
        );
        $this->dom->addChild(
            $node,
            'tipos:Status',
            $rps->status,
            true
        );
        $this->addRpsSubstituido($node, $rps);

        $parent->appendChild($node);
    }

    /**
     * Includes IdentificacaoRps TAG in parent NODE
     * @param DOMNode $parent
     * @param stdClass $rps
     */
    protected function addIdentificacaoRps(DOMNode &$parent, stdClass $rps)
    {
        if (empty($rps->identificacaorps)) {
            return;
        }

        $id = $rps->identificacaorps;
        $node = $this->dom->createElement('tipos:IdentificacaoRps');
        $this->dom->addChild($node, 'tipos:Numero', $id->numero, true);
        $this->dom->addChild($node, 'tipos:Serie', $id->serie, true);
        $this->dom->addChild($node, 'tipos:Tipo', $id->tipo, true);
        $parent->appendChild($node);
    }

    /**
     * Includes RpsSubstituido TAG in parent NODE
     * @param DOMNode $parent
     * @param stdClass $rps
     */
    protected function addRpsSubstituido(DOMNode &$parent, stdClass $rps)
    {
        if (empty($rps->rpssubstituido)) {
            return;
        }

        $id = $rps->rpssubstituido;
        $node = $this->dom->createElement('tipos:RpsSubstituido');
        $this->dom->addChild($node, 'tipos:Numero', $id->numero, true);
        $this->dom->addChild($node, 'tipos:Serie', $id->serie, true);
        $this->dom->addChild($node, 'tipos:Tipo', $id->tipo, true);
        $parent->appendChild($node);
    }

    /**
     * Includes Prestador TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addPrestador(DOMNode &$parent)
    {
        $prestador = isset($this->inf->prestador) ? $this->inf->prestador : null;
        if (empty($prestador) && !isset($this->config)) {
            return;
        }

        $node = $this->dom->createElement('tipos:Prestador');
        $cpfcnpj = $this->dom->createElement('tipos:CpfCnpj');

        if (!empty($prestador->cpfcnpj)) {
            $this->appendCpfCnpj($cpfcnpj, $prestador->cpfcnpj);
        } elseif (!empty($this->config->cnpj)) {
            $this->dom->addChild($cpfcnpj, 'tipos:Cnpj', $this->config->cnpj, true);
        }

        $node->appendChild($cpfcnpj);
        $this->dom->addChild(
            $node,
            'tipos:InscricaoMunicipal',
            !empty($prestador->inscricaomunicipal) ? $prestador->inscricaomunicipal : $this->config->im,
            false
        );
        $parent->appendChild($node);
    }

    /**
     * Includes Servico TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addServico(DOMNode &$parent)
    {
        $serv = $this->inf->servico;
        $val = $serv->valores;
        $node = $this->dom->createElement('tipos:Servico');
        $valnode = $this->dom->createElement('tipos:Valores');

        $this->dom->addChild(
            $valnode,
            'tipos:ValorServicos',
            $this->formatMoney($val->valorservicos),
            true
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValorDeducoes',
            isset($val->valordeducoes) ? $this->formatMoney($val->valordeducoes) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValorPis',
            isset($val->valorpis) ? $this->formatMoney($val->valorpis) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValorCofins',
            isset($val->valorcofins) ? $this->formatMoney($val->valorcofins) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValorInss',
            isset($val->valorinss) ? $this->formatMoney($val->valorinss) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValorIr',
            isset($val->valorir) ? $this->formatMoney($val->valorir) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValorCsll',
            isset($val->valorcsll) ? $this->formatMoney($val->valorcsll) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:OutrasRetencoes',
            isset($val->outrasretencoes) ? $this->formatMoney($val->outrasretencoes) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValTotTributos',
            isset($val->valtottributos) ? $this->formatMoney($val->valtottributos) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:ValorIss',
            isset($val->valoriss) ? $this->formatMoney($val->valoriss) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:Aliquota',
            isset($val->aliquota) ? $val->aliquota : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:DescontoIncondicionado',
            isset($val->descontoincondicionado) ? $this->formatMoney($val->descontoincondicionado) : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            'tipos:DescontoCondicionado',
            isset($val->descontocondicionado) ? $this->formatMoney($val->descontocondicionado) : null,
            false
        );

        $node->appendChild($valnode);
        $this->dom->addChild(
            $node,
            'tipos:IssRetido',
            $serv->issretido,
            true
        );
        $this->dom->addChild(
            $node,
            'tipos:ResponsavelRetencao',
            isset($serv->responsavelretencao) ? $serv->responsavelretencao : null,
            false
        );
        $this->dom->addChild(
            $node,
            'tipos:ItemListaServico',
            $serv->itemlistaservico,
            true
        );
        $this->dom->addChild(
            $node,
            'tipos:CodigoCnae',
            isset($serv->codigocnae) ? $serv->codigocnae : null,
            false
        );
        $this->dom->addChild(
            $node,
            'tipos:CodigoTributacaoMunicipio',
            isset($serv->codigotributacaomunicipio) ? $serv->codigotributacaomunicipio : null,
            false
        );
        $this->dom->addChild(
            $node,
            'tipos:Discriminacao',
            $serv->discriminacao,
            true
        );
        $this->dom->addChild(
            $node,
            'tipos:CodigoMunicipio',
            $serv->codigomunicipio,
            true
        );
        $this->dom->addChild(
            $node,
            'tipos:CodigoPais',
            isset($serv->codigopais) ? $serv->codigopais : null,
            false
        );
        $this->dom->addChild(
            $node,
            'tipos:ExigibilidadeISS',
            $serv->exigibilidadeiss,
            true
        );
        $this->dom->addChild(
            $node,
            'tipos:MunicipioIncidencia',
            isset($serv->municipioincidencia) ? $serv->municipioincidencia : null,
            false
        );

        $parent->appendChild($node);
    }

    /**
     * Includes TomadorServico TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addTomadorServico(DOMNode &$parent)
    {
        if (!isset($this->inf->tomadorservico)) {
            return;
        }

        $tom = $this->inf->tomadorservico;
        $node = $this->dom->createElement('tipos:TomadorServico');

        if (!empty($tom->identificacaotomador)) {
            $ide = $this->dom->createElement('tipos:IdentificacaoTomador');
            $cpfcnpj = $this->dom->createElement('tipos:CpfCnpj');
            $this->appendCpfCnpj($cpfcnpj, $tom->identificacaotomador->cpfcnpj);
            $ide->appendChild($cpfcnpj);
            $this->dom->addChild(
                $ide,
                'tipos:InscricaoMunicipal',
                isset($tom->identificacaotomador->inscricaomunicipal)
                    ? $tom->identificacaotomador->inscricaomunicipal
                    : null,
                false
            );
            $node->appendChild($ide);
        }

        $this->dom->addChild(
            $node,
            'tipos:RazaoSocial',
            $tom->razaosocial,
            true
        );

        if (!empty($tom->endereco)) {
            $end = $tom->endereco;
            $endereco = $this->dom->createElement('tipos:Endereco');
            $this->dom->addChild($endereco, 'tipos:Endereco', $end->endereco, true);
            $this->dom->addChild($endereco, 'tipos:Numero', $end->numero, true);
            $this->dom->addChild(
                $endereco,
                'tipos:Complemento',
                isset($end->complemento) ? $end->complemento : null,
                false
            );
            $this->dom->addChild($endereco, 'tipos:Bairro', $end->bairro, true);
            $this->dom->addChild($endereco, 'tipos:CodigoMunicipio', $end->codigomunicipio, true);
            $this->dom->addChild($endereco, 'tipos:Uf', $end->uf, true);
            $this->dom->addChild($endereco, 'tipos:Cep', $this->formatCep($end->cep), true);
            $node->appendChild($endereco);
        }

        if (!empty($tom->contato)) {
            $contato = $this->dom->createElement('tipos:Contato');
            $this->dom->addChild(
                $contato,
                'tipos:Telefone',
                isset($tom->contato->telefone) ? $tom->contato->telefone : null,
                false
            );
            $this->dom->addChild(
                $contato,
                'tipos:Email',
                isset($tom->contato->email) ? $tom->contato->email : null,
                false
            );
            $node->appendChild($contato);
        }

        $parent->appendChild($node);
    }

    /**
     * Includes Intermediario TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addIntermediario(DOMNode &$parent)
    {
        if (!isset($this->inf->intermediario)) {
            return;
        }

        $int = $this->inf->intermediario;
        $node = $this->dom->createElement('tipos:Intermediario');
        $ide = $this->dom->createElement('tipos:IdentificacaoIntermediario');
        $cpfcnpj = $this->dom->createElement('tipos:CpfCnpj');
        $this->appendCpfCnpj($cpfcnpj, $int->identificacaointermediario->cpfcnpj);
        $ide->appendChild($cpfcnpj);
        $this->dom->addChild(
            $ide,
            'tipos:InscricaoMunicipal',
            isset($int->identificacaointermediario->inscricaomunicipal)
                ? $int->identificacaointermediario->inscricaomunicipal
                : null,
            false
        );
        $node->appendChild($ide);
        $this->dom->addChild($node, 'tipos:RazaoSocial', $int->razaosocial, true);
        $this->dom->addChild($node, 'tipos:CodigoMunicipio', $int->codigomunicipio, true);
        $parent->appendChild($node);
    }

    /**
     * Includes ConstrucaoCivil TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addConstrucao(DOMNode &$parent)
    {
        if (!isset($this->inf->construcaocivil)) {
            return;
        }

        $obra = $this->inf->construcaocivil;
        $node = $this->dom->createElement('tipos:ConstrucaoCivil');
        $this->dom->addChild(
            $node,
            'tipos:CodigoObra',
            isset($obra->codigoobra) ? $obra->codigoobra : null,
            false
        );
        $this->dom->addChild(
            $node,
            'tipos:Art',
            isset($obra->art) ? $obra->art : null,
            false
        );
        $parent->appendChild($node);
    }

    /**
     * Append Cpf or Cnpj child to CpfCnpj node
     * @param DOMElement $parent
     * @param stdClass $cpfcnpj
     */
    protected function appendCpfCnpj(DOMElement &$parent, stdClass $cpfcnpj)
    {
        if (!empty($cpfcnpj->cnpj)) {
            $this->dom->addChild($parent, 'tipos:Cnpj', $cpfcnpj->cnpj, true);
            return;
        }

        $this->dom->addChild($parent, 'tipos:Cpf', $cpfcnpj->cpf, true);
    }

    /**
     * Format monetary values
     * @param float $value
     * @return string
     */
    protected function formatMoney($value)
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Format CEP with leading zeros
     * @param string|int $cep
     * @return string
     */
    protected function formatCep($cep)
    {
        return str_pad(preg_replace('/\D/', '', (string) $cep), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Build reference Id for RPS digital signature
     * @return string
     */
    protected function getInfDeclaracaoId()
    {
        if (!empty($this->inf->id)) {
            return (string) $this->inf->id;
        }

        if (!empty($this->inf->rps->identificacaorps->numero)) {
            return 'rps' . $this->inf->rps->identificacaorps->numero;
        }

        return 'rps' . uniqid();
    }
}
