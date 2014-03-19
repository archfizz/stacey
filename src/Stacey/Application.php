<?php

namespace Stacey;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class Application extends Kernel
{
    /**
     * @var string
     */
    public static $version = '4.0.0-dev';

    /**
     * @var string
     */
    public $route;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Container
     */
    protected $container;

    public function registerBundles()
    {
        return array(new CoreBundle());
    }

    private function getAppDir()
    {
        return $this->getRootDir() . '/../../app';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getAppDir() . '/config/config_'.$this->getEnvironment().'.yml');
    }

    public function getCacheDir()
    {
        return $this->getAppDir() . '/_cache';
    }

    public function getLogDir()
    {
        return $this->getAppDir() . '/_logs';
    }

    public function run()
    {
        $get = $this->request->query->all();
        $this->fallbackOnDefaultTimezone();

        // it's easier to handle some redirection through php rather than relying on a more complex .htaccess file to do all the work
        if ($this->handleRedirects()) {
            return;
        }

        // strip any leading or trailing slashes from the passed url
        $key = key($get);

        // if the key isn't a URL path, then ignore it
        if (!preg_match('/\//', $key)) {
            $key = false;
        }

        $key = preg_replace(array('/\/$/', '/^\//'), '', $key);

        // store file path for this current page
        $this->route = isset($key) ? $key : 'index';

        // TODO: Relative root path is set incorrectly (missing an extra ../)
        // strip any trailing extensions from the url
        $this->route = preg_replace('/[\.][\w\d]+?$/', '', $this->route);
        $filePath = Helpers::url_to_file_path($this->route);

        var_dump($filePath);

        try {
            // create and render the current page
            $this->createPage($filePath);
        } catch (\Exception $e) {
            if ($e->getMessage() == "404") {
                // return 404 headers

                $notFoundResponse = new Response();
                $notFoundResponse->setStatusCode(Response::HTTP_NOT_FOUND);

                if (file_exists(Config::$content_folder.'/404')) {
                    $this->route = '404';
                    $this->createPage(Config::$content_folder.'/404', 404);
                } elseif (file_exists(Config::$root_folder.'public/404.html')) {
                    $notFoundResponse->setContent( file_get_contents(Config::$root_folder.'public/404.html') );
                } else {
                    $notFoundResponse->setContent('<h1>404</h1><h2>Page could not be found.</h2><p>Unfortunately, the page you were looking for does not exist here.</p>');
                }

                $notFoundResponse->send();
                exit();

            } else {
                echo '<h3>'.$e->getMessage().'</h3>';
            }
        }
    }

    /**
     * @return boolean
     */
    private function handleRedirects()
    {
        $requestUri = $this->request->getRequestUri();

        // rewrite any calls to /index or /app back to /
        if (preg_match('/^\/?(index|app)\/?$/', $requestUri)) {
            $response = new RedirectResponse('../', Response::HTTP_MOVED_PERMANENTLY);
            $response->send();

            return true;
        }

        if (!preg_match('/\/$/', $requestUri) && !preg_match('/[\.\?\&][^\/]+$/', $requestUri)) {
            $response = new RedirectResponse($requestUri.'/', Response::HTTP_MOVED_PERMANENTLY);
            $response->send();

            return true;
        }

        return false;
    }

    /**
     * In PHP 5.3.0 they added a requisite for setting a default timezone, this
     * should be handled via the php.ini, but as we cannot rely on this, we have
     *  to set a default timezone ourselves
     */
    private function fallbackOnDefaultTimezone()
    {
        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set($this->container->timezone);
        }
    }

    /**
     * @param string $templateFile
     *
     * @return string
     */
    private function getContentType($templateFile)
    {
        $fileinfo = new \SplFileInfo($templateFile);

        switch ($fileinfo->getExtension()) {
            case 'txt':
                return "text/plain";

            case 'atom':
                return "application/atom+xml";

            case 'rss':
                return "application/rss+xml";

            case 'rdf':
                return "application/rdf+xml";

            case 'xml':
                return "text/xml";

            case 'json':
                return "application/json";

            case 'css':
                return "text/css";

            default:
                return "text/html";
        }
    }

    /**
     * @param Cache $cache
     *
     * @return boolean
     */
    private function etagHasExpired(Cache $cache)
    {
        // Safari incorrectly caches 304s as empty pages, so don't serve it 304s
        if (strpos($this->request->headers->get('User-Agent'), 'Safari') !== false) {
            return true;
        }

        $ifNoneMatch = $this->request->headers->get('If-None-Match');

        // Check for a local cache
        return (! ($ifNoneMatch && stripslashes($ifNoneMatch) === $cache->hash));
    }

    /**
     * @param string $filePath
     * @param string $templateFile
     * @param integer $statusCode
     *
     * @return Response
     */
    private function renderResponse($filePath, $templateFile, $statusCode = 200)
    {
        $response = new Response();

        $response->headers->set('Content-Type', $this->getContentType($templateFile));
        $response->headers->set('Generator', sprintf('stacey-v%s', static::$version));

        $cache = new Cache($filePath, $templateFile);

        // if etag is still fresh, return 304 and don't render anything
        $response->headers->set('Etag', $cache->hash);

        if (!$this->etagHasExpired($cache)) {
            $response->headers->set('Content-Length', 0);
            $response->setStatusCode(Response::HTTP_NOT_MODIFIED);

            return $response;
        }

        // if cache has expired
        if ($cache->expired()) {
            // render page & create new cache
            $content = $cache->create($this->route);
        } else {
            // render the existing cache
            $content = $cache->render();
        }

        $response->setContent($content);
        $response->setStatusCode($statusCode);

        return $response;
    }

    /**
     * @param string $filePath
     * @param integer $statusCode
     */
    public function createPage($filePath, $statusCode = 200)
    {
        // return a 404 if a matching folder doesn't exist
        if (!file_exists($filePath)) {
            throw new \Exception('404');
        }

        // TO-DO: remove globals!

        // register global for the path to the page which is currently being viewed
        global $current_page_filePath;
        $current_page_filePath = $filePath;

        // register global for the template for the page which is currently being viewed
        global $current_page_template_file;
        $template_name = Page::template_name($filePath);
        $current_page_template_file = Page::template_file($template_name);

        // error out if template file doesn't exist (or glob returns an error)
        if (empty($template_name)) {
            throw new \Exception('404');
        }

        // render page
        $response = $this->renderResponse($filePath, $current_page_template_file, $statusCode);
        $response->send();
    }
}
