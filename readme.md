# Router

## Описание работы

Имеет похожий на Silex алгоритм работы

## Пример использования


```php
require_once __DIR__ . '/../vendor/autoload.php';

class ApiController implements ControllerInterface
{
    public function connect(Router $router)
    {
        $router->before(function($request) {
            return ['before apicontroller'. PHP_EOL];
        });
        
        $router->get('/test', function (Request $request) {
            return ['/test'];
        });
        
            
        $router->get('/test2/{supplier}/get/{id}', function (Request $request) {
            var_dump($request->get('supplier'));
            var_dump($request->get('id'));
            var_dump($request->get('var'));
            return ['/test2'];
        });
            
                
        $router->get('/test3', function (Request $request) {
            return ['/test3'];
        });
        
        $router->post('/post', function (Request $request) {
            var_dump($request->getContent());
            return ['/post'];
        });
    }
}

$controller = new ApiController();

$router = new Router();
$router->mount('/v1/', $controller);

$router->view(function ($response, Request $request) {
    return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
});

$router->error(function (\Exception $exception, Request $request, $code) {
    return json_encode(["code" => $code, "text" => $exception->getMessage()]);
});

$router->run();
```
## Тестирование

```bash
httperf --server simple-router.raketa.dev --port 80 --uri /v1/test --rate 2000 --num-conn=4000 --num-call=1 --timeout 2
```