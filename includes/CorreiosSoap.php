<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CorreiosSoap
{
    private static $soapClient;
    private $envelopeSoap;
    private $objects = null;

    public function __construct(SoapConfig $soapClient , $objects, $type = null)
    {
        self::$soapClient = $soapClient;
        if($type == 'buscaEventosLista'){
            $this->objects = $objects;
        }else{
            $this->objects = $objects[0];
        }

        $this->TWFormatEnvelopeSoap();
    }

    public function TWFormatEnvelopeSoap()
    {
        $this->envelopeSoap = [
            'usuario' => "ECT",
            'senha' => "SRO",
            'tipo' => "L",
            'resultado' => "T",
            'lingua' => "101",
            'objetos' => $this->objects
        ];
    }

    public function getResult($type = null)
    {
        $arrayItens = [];

        if($type == 'buscaEventosLista'){
            $res = self::$soapClient->buscaEventosLista($this->envelopeSoap);
            foreach ($res->return->objeto as $item):
                $arrayItens[][$item->numero] = $item->evento[0]->descricao;
            endforeach;
        }else{
            $res = self::$soapClient->buscaEventos($this->envelopeSoap);
            $arrayItens[][$res->return->objeto->numero] = $res->return->objeto->evento[0]->descricao;
        }

        return $arrayItens;
    }
}