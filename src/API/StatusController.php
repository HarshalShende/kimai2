<?php


/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\API;

use App\API\Model\Plugin;
use App\API\Model\Version;
use App\Plugin\PluginManager;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(name="Default")
 *
 * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
 */
class StatusController extends BaseApiController
{
    /**
     * @var ViewHandlerInterface
     */
    private $viewHandler;

    public function __construct(ViewHandlerInterface $viewHandler)
    {
        $this->viewHandler = $viewHandler;
    }

    /**
     * A testing route for the API
     *
     * @OA\Response(
     *     response=200,
     *     description="A simple route that returns a 'pong', which you can use for testing the API",
     *     @OA\JsonContent(example="{'message': 'pong'}")
     * )
     *
     * @Rest\Get(path="/ping")
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function pingAction(): Response
    {
        $view = new View(['message' => 'pong'], 200);

        return $this->viewHandler->handle($view);
    }

    /**
     * Returns information about the Kimai release
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns version information about the current release",
     *     @OA\JsonContent(ref=@Model(type=Version::class))
     * )
     *
     * @Rest\Get(path="/version")
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function versionAction(): Response
    {
        return $this->viewHandler->handle(new View(new Version(), 200));
    }

    /**
     * Returns information about installed Plugins
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns a list of plugin names and versions",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Plugin::class))
     *      )
     * )
     *
     * @Rest\Get(path="/plugins")
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function pluginAction(PluginManager $pluginManager): Response
    {
        $plugins = [];
        foreach ($pluginManager->getPlugins() as $plugin) {
            $pluginManager->loadMetadata($plugin);
            $plugins[] = new Plugin($plugin);
        }

        return $this->viewHandler->handle(new View($plugins, 200));
    }
}
