<?php

namespace BSer\Router;

class Request
{
    private $server;
    
    /**
     * Значения подстановочных слов в пути
     * @var array
     */
    private $params = [];
    
    private $getParams  = [];
    
    private $postParams  = [];
    
    /**
     * POST body
     * @var string
     */
    private $content;
    
    public static function createFromGlobals()
    {
        $request = new self;
        $request->server = $_SERVER;
        $request->getParams = $_GET;
        $request->postParams = $_POST;
        $request->content = file_get_contents('php://input');
        
        return $request;
    }
    
    /**
     * Url без GET-параметров
     * @return string
     */
    public function getPathInfo()
    {
        return $this->server['DOCUMENT_URI'];
    }
    
    public function getMethod()
    {
        return $this->server['REQUEST_METHOD'];
    }

    /**
     * Добавляет значения параметров, извлеченных из строки запроса
     * @param array $params
     */
    public function addParams($params)
    {
        $this->params += $params;
    }
    
    /**
     * Возвращает значение подстановочного слова из строки запроса или значение GET\POST-параметра
     * @param string $paramName
     */
    public function get($paramName)
    {
        $result = null;
        
        $result = $this->params[$paramName] ?? null;
        
        if (!isset($result)) {
            $result = $this->getParams[$paramName] ?? null;
        }
        
        if (!isset($result)) {
            $result = $this->postParams[$paramName] ?? null;
        }
        
        return $result;
    }
    
    public function getContent()
    {
        return $this->content;
    }
}
