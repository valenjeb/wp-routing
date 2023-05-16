<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use Devly\DI\Contracts\IContainer;
use Devly\DI\Exceptions\ResolverException;
use Devly\Utils\Str;
use Devly\WP\Routing\Contracts\IRequest;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

use function add_filter;
use function apply_filters;
use function get_bloginfo;
use function sprintf;

abstract class Controller
{
    /** @internal special parameter key */
    public const FLASH_KEY = '_fid';
    protected IContainer $container;
    protected IRequest $request;
    protected ?string $pageTitle   = null;
    protected ?string $pageContent = null;
    protected ?string $docTitle    = null;
    protected string $view;
    /** @var array<string, mixed>  */
    protected array $metadata = [];
    /** @var array<string, mixed> */
    protected array $params;
    /** @var WP_Post|WP_Post_Type|WP_Term|WP_User|null */
    protected $queriedObject;

    public function injectDefaults(IContainer $container): void
    {
        $this->container = $container;
    }

    /**
     * @return mixed|void
     *
     * @throws ResolverException
     */
    public function run(IRequest $request)
    {
        $this->request = $request;
        $this->initGlobalParameters();

        $this->beforeRender();

        $this->addFilters();

        $method = 'render' . Str::ucfirst($this->view);

        try {
            $rm = new ReflectionMethod($this, $method);
        } catch (ReflectionException $e) {
            return;
        }

        if ($rm->isAbstract() || $rm->isStatic() || $rm->isPrivate() || $rm->isProtected()) {
            throw new RuntimeException(sprintf(
                'The "%s" render method %s::%s() must be a public and non static method.',
                $this->view,
                static::class,
                $method
            ));
        }

        return $this->container->call([$this, $method], $request->getQueryVars());
    }

    public function setDocumentTitle(string $title): void
    {
        $this->docTitle = $title;
    }

    public function setPageTitle(string $title): void
    {
        $this->pageTitle = $title;
    }

    public function setPageContent(string $content): void
    {
        $this->pageContent = $content;
    }

    /** @return WP_Post|WP_Post_Type|WP_Term|WP_User|null */
    final protected function getQueriedObject(): ?object
    {
        return $this->queriedObject ?? null;
    }

    protected function addFilters(): void
    {
        if (is_archive()) {
            add_filter('get_the_archive_title', [$this, 'filterTheArchiveTitle'], 10);

            add_filter('get_the_archive_description', [$this, 'filterTheArchiveDescription'], 10);
        } elseif (is_singular() || is_single()) {
            add_filter('the_title', [$this, 'filterTheTitle'], 10);

            add_filter('the_content', [$this, 'filterTheContent'], 10);

            add_filter('get_post_metadata', [$this, 'filterPostMetadata'], 10, 4);
        }

        add_filter('pre_get_document_title', [$this, 'filterGetDocumentTitle'], 10);
    }

    protected function initGlobalParameters(): void
    {
        $this->params = [];

        $this->getRequest()->getQueryVars();

        $this->queriedObject = get_queried_object();

        $this->view = $this->request->getRoute()->getParameter('view', 'default');
    }

    protected function beforeRender(): void
    {
    }

    protected function getPageTitle(string $title): string
    {
        return $this->pageTitle ?: $title ?: __('Untitled', 'devly-routing');
    }

    /**
     * @param string $key   Meta key to set
     * @param mixed  $value Meta value
     */
    public function setMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * @param string|null $key     The meta key name
     * @param mixed       $default Default value to return if key does not exist
     *
     * @return mixed
     */
    public function getMetadata(?string $key = null, $default = null)
    {
        if (! isset($this->metadata['_yoast_wpseo_title'])) {
            $this->metadata['_yoast_wpseo_title'] = $this->getdocumenttitle('');
        }

        $metadata = apply_filters(Hooks::FILTER_CONTROLLER_METADATA, $this->metadata);

        if (! $key) {
            return $metadata;
        }

        return $metadata[$key] ?? $default;
    }

    /** @param string $default Default value to return if document title not set. */
    protected function getDocumentTitle(string $default): string
    {
        return $this->docTitle ?: $this->getPageTitle($default);
    }

    public function filterTheTitle(string $title): string
    {
        if (get_the_ID() !== $this->getQueriedObject()->ID) {
            return $title;
        }

        return $this->getPageTitle($title);
    }

    public function filterTheArchiveTitle(string $title): string
    {
        if (! isset($this->pageTitle)) {
            return $title;
        }

        return $this->pageTitle;
    }

    public function filterTheArchiveDescription(string $description): ?string
    {
        if (! isset($this->pageContent)) {
            return $description;
        }

        return $this->pageContent;
    }

    public function filterTheContent(string $_content): string
    {
        if (get_the_ID() !== $this->getQueriedObject()->ID) {
            return $_content;
        }

        return $this->pageContent ?: $_content;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function filterPostMetadata($value, int $postID, string $key, bool $single)
    {
        if ($postID !== $this->getQueriedObject()->ID) {
            return $value;
        }

        if ($key) {
            return $this->getMetadata($key);
        }

        return $this->getMetadata();
    }

    public function filterGetDocumentTitle(string $title): string
    {
        $sep   = apply_filters('document_title_separator', '-');
        $title = $this->getDocumentTitle($title);
        $site  = get_bloginfo('name', 'display');

        return sprintf('%s %s %s', $title, $sep, $site);
    }

    protected function getRequest(): IRequest
    {
        if (! isset($this->request)) {
            throw new RuntimeException('Request not set.');
        }

        return $this->request;
    }
}
