<?php

namespace BSer\Router;

class Router
{
    const URL_OPTIONS_PATTERN = '/{([\w]+)}/';
    
    /**
     * Список контроллеров ([ 'префикс' => controller])
     * @var ControllerInterface[]
     */
    private $controllers;
    
    /**
     * Функция преобразования ответа, полученного от обработчика endpoint-а (если не указана, возвращается то, что вернул обработчик)
     * @var callable
     */
    private $viewCallable;
    
    private $errorCallable;
    
    private $beforeCallable;
    
    /**
     * Список маршрутов (endpoints) с их обработчиками ([endpoint => callable])
     * @var array
     */
    private $routes = [];
    
    /**
     * Нужен для формирования полного имени маршрута - с префиксом, указываемым в mount()
     * @var string
     */
    private $matchedControllerPrefix;
    
    /**
     * 
     * @var ControllerInterface
     */
    private $matchedController;
    
    private $silentMode = false;
    
    public function mount($prefixURL, ControllerInterface $controller)
    {
        $this->controllers[$prefixURL] = $controller;
    }
    
    /**
     * Задает функцию, вызываемую перед выполнением обработчика соответствующего маршрута (полезно для журналирования)
     * @param callable $callable
     */
    public function before(Callable $callable)
    {
        $this->beforeCallable = $callable;
    }
    
    public function view(Callable $callable)
    {
        $this->viewCallable = $callable;
    }
    
    public function error(Callable $callable)
    {
        $this->errorCallable = $callable;
    }
    
    public function get($url, Callable $callable)
    {
        $this->addRoute('GET', $url, $callable);
    }
    
    public function post($url, Callable $callable)
    {
        $this->addRoute('POST', $url, $callable);
    }
    
    public function setSilentMode()
    {
        $this->silentMode = true;
    }
    
    public function run() {
        $request = Request::createFromGlobals();
        
        $this->findMatchedController($request);
        
        $controller = $this->matchedController;
        
        if (!$controller) {
            $this->errorExec(new Exception\ControllerNotFoundException('controller not found'), $request, 404);
        }
        
        $controller->connect($this);
        
        $callable = $this->getMatchedCallable($request);
        
        if (is_callable($callable)) {
            
            try {
                $this->beforeExec($request);
                
                $response = $callable($request);
                
                $this->viewExec($response, $request);
                
            } catch (\Exception $exception) {
                $this->errorExec($exception, $request, 500);
            }
            
        } else {
            $this->errorExec(new Exception\ControllerNotFoundException('operation not found'), $request, 404);
        }
    }
    
    protected function addRoute($method, $url, Callable $callable)
    {
        $url = rtrim($this->matchedControllerPrefix, '/') . $url;
        
        $this->routes[$method][$url] = $callable;
    }
    
    /**
     * Возвращает найденный обработчик для запроса и добавляет к запросу подставляемые параметры пути, если обработчик найден 
     * @param Request $request
     * @return NULL|array|mixed
     */
    protected function getMatchedCallable(Request $request)
    {
        $callable = null;
        $routes = $this->routes[$request->getMethod()] ?? [];
        
        foreach($routes as $route => $routeCallable) {
            
            $preparedRoute = $this->prepareRoute($route);
            
            if (preg_match('~^'.$preparedRoute.'$~', $request->getPathInfo())) {
                $callable = $routeCallable;
                $this->addRequestParamsFromRoute($route, $request);
                break;
            }
        }
        
        return $callable;
    }
    
    protected function viewExec($response, Request $request)
    {
        if ($this->silentMode) {
            return;
        }
        
        if (is_callable($this->viewCallable)) {
            echo ($this->viewCallable)($response, $request);
        } else {
            echo $response;
        }
    }
    
    protected function errorExec(\Exception $exception, Request $request, $code)
    {
        if ($this->silentMode) {
            return;
        }
        
        http_response_code($code);
        if (is_callable($this->errorCallable)) {
            echo ($this->errorCallable)($exception, $request, $code);
        } else {
            echo $exception->getMessage();
        }
    }
    
    protected function beforeExec(Request $request)
    {
        if (is_callable($this->beforeCallable)) {
            ($this->beforeCallable)($request);
        }
    }
    
    protected function findMatchedController(Request $request)
    {
        $this->matchedController = null;
        $this->matchedControllerPrefix = null;
        
        foreach($this->controllers as $prefixURL => $controller) {
            if (strpos($request->getPathInfo(), $prefixURL) == 0) {
                $this->matchedControllerPrefix = $prefixURL;
                $this->matchedController = $controller;
                break;
            }
        }
        
        return $this->matchedController;
    }
    
    /**
     * Формирует регулярное выражение, заменяя подстановочные слова в пути (например, {id}) на регулярки, если они есть
     * @param string $route
     * @param Request $request
     * @return string
     */
    private function prepareRoute($route)
    {
        $pattern = $route;
        
        $urlParamsNames = $this->getUrlParamsNames($route);
        
        if ($urlParamsNames) {
            $pattern = preg_replace(self::URL_OPTIONS_PATTERN, '([^\/\s]*)', $route);
        }
        
        return $pattern;
    }
    
    /**
     * Добавляет в запрос значения подстановочных слов для дальнейшего возможного их извлечения обработчиком 
     * @param string $route
     * @param Request $request
     */
    private function addRequestParamsFromRoute($route, Request $request)
    {
        $urlParamsNames = $this->getUrlParamsNames($route);
        $pattern = $this->prepareRoute($route, $request);
        
        $urlParamsValues = [];
        preg_match('~'.$pattern.'~', $request->getPathInfo(), $urlParamsValues);
        array_shift($urlParamsValues);

        if (count($urlParamsNames) != count($urlParamsValues)) {
            return;
        }
        
        $params = array_combine($urlParamsNames, $urlParamsValues);
        
        $request->addParams($params);
    }
    
    /**
     * Возвращает список подстановочных слов из endpoint
     * @param string $route
     * @return array
     */
    private function getUrlParamsNames($route)
    {
        $urlParamsNames = [];
        preg_match_all(self::URL_OPTIONS_PATTERN, $route, $urlParamsNames);
        $urlParamsNames = $urlParamsNames[1];
        
        return $urlParamsNames;
    }
}

