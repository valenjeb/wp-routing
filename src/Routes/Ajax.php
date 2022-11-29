<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Routes;

use Devly\DI\Contracts\IContainer;
use Devly\Utils\Pipeline;
use Devly\WP\Routing\Contracts\IRequest;
use Nette\Http\Response;
use RuntimeException;
use Throwable;

use function add_action;
use function admin_url;
use function http_build_query;
use function is_callable;
use function is_object;
use function sprintf;

class Ajax extends RouteBase
{
    protected bool $adminOnly = false;

    /**
     * @param string                                                          $action   Ajax action name
     * @param callable|class-string|string|array<class-string|object, string> $callback The route controller
     */
    public function __construct(string $action, $callback)
    {
        $this->pattern = $action;
        $this->name($action);
        $this->controller = $callback;
    }

    public function setAdminOnly(bool $adminOnly = true): self
    {
        $this->adminOnly = $adminOnly;

        return $this;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }

    /** @inheritdoc  */
    public function url(array $args = []): string
    {
        $query = empty($args) ? '' : '&' . http_build_query($args);

        return admin_url('admin-ajax.php') . '?action=' . $this->getPattern() . $query;
    }

    public function run(IContainer $container): void
    {
        if (! $this->isAdminOnly()) {
            add_action('wp_ajax_nopriv_' . $this->getPattern(), fn () => $this->execute($container));
        }

        add_action('wp_ajax_' . $this->getPattern(), fn () => $this->execute($container));
    }

    protected function execute(?IContainer $container = null): void
    {
        $response = Pipeline::create($container)
            ->send($container[IRequest::class])
            ->through($this->middleware())
            ->then(function (IRequest $request) use ($container) {
                try {
                    $controller = $this->normalizeController($this->controller());

                    if (is_callable($controller)) {
                        return $container->call(
                            $controller,
                            $request->getHttpRequest()->getQuery() + $this->getParameters()
                        );
                    }

                    [$class, $method] = $controller;

                    $object = is_object($class) ? $class : $container->make($class);

                    return $container->call(
                        [$object, $method],
                        $request->getHttpRequest()->getQuery() + $this->getParameters()
                    );
                } catch (Throwable $e) {
                    throw new RuntimeException(
                        sprintf('An error occurred during route "%s" execution.', $this->name()),
                        $e->getCode(),
                        $e
                    );
                }
            });

        try {
            $response = $this->ensureResponse($response);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('Route "%s" returned invalid response.', $this->name()),
                $e->getCode(),
                $e
            );
        }

        $response->send($container[IRequest::class], $container[Response::class]);

        wp_die();
    }
}
