<?php
/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Framework;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Templating\Helper\CoreAssetsHelper;
use Chamilo\CoreBundle\Component\Editor\Editor;

/**
 * Class Container
 * This class is a way to access Symfony2 services in legacy Chamilo code.
 * @package Chamilo\CoreBundle\Framework
 */
class Container
{
    /**
     * @var ContainerInterface
     */
    public static $container;
    public static $session;
    public static $request;
    public static $configuration;
    public static $urlGenerator;
    public static $security;
    public static $translator;
    public static $mailer;
    public static $template;

    public static $rootDir;
    public static $logDir;
    public static $tempDir;
    public static $dataDir;
    public static $courseDir;
    public static $configDir;
    public static $assets;
    public static $htmlEditor;
    public static $twig;
    public static $roles;

    /**
     * @param ContainerInterface $container
     */
    public static function setContainer($container)
    {
        self::$container = $container;
    }

    /**
     * @return string
     */
    public static function getConfigDir()
    {
        return self::$configDir;
    }

    /**
     * @return RoleHierarchy
     */
    public static function getRoles()
    {
        return self::$roles;
    }

    /**
     * @return string
     */
    public static function getLogDir()
    {
        return self::$logDir;
    }

    /**
     * @return string
     */
    public static function getTempDir()
    {
        return self::$tempDir;
    }

    /**
     * @return string
     */
    public static function getRootDir()
    {
        return self::$rootDir;
    }

    /**
     * @return string
     */
    public static function getDataDir()
    {
        return self::$dataDir;
    }

    /**
     * @return string
     */
    public static function getCourseDir()
    {
        return self::$courseDir;
    }

    /**
     * @return \Twig_Environment
     */
    public static function getTwig()
    {
        return self::$twig;
    }

    /**
     * @return Editor
     */
    public static function getHtmlEditor()
    {
        return self::$htmlEditor;
    }

    /**
     * @return UrlGeneratorInterface
     */
    public static function getUrlGenerator()
    {
        return self::$urlGenerator;
    }

    /**
     * @return Request
     */
    public static function getRequest()
    {
        return self::$container->get('request');
    }

    /*public static function setRequest($request)
    {
        self::$request = $request;
    }*/

    /**
     * @return Session
     */
    public static function getSession()
    {
        return self::$session;
    }

    /**
     * @param SessionInterface $session
     */
    public static function setSession($session)
    {
        self::$session = $session;
    }

    /**
     * @return SecurityContextInterface
     */
    public static function getSecurity()
    {
        return self::$security;
    }

    /**
     * @return Translator
     */
    public static function getTranslator()
    {
        return self::$translator;
    }

    /**
     * @return CoreAssetsHelper
     */
    public static function getAsset()
    {
        return self::$assets;
    }

    /**
     * @return \Swift_Mailer
     */
    public static function getMailer()
    {
       return self::$mailer;
    }

    /**
     * @return \Elao\WebProfilerExtraBundle\TwigProfilerEngine
     */
    public static function getTemplate()
    {
        return self::$container->get('templating');
    }

    /**
     * @return \Chamilo\SettingsBundle\Manager\SettingsManager
     */
    public static function getSettingsManager()
    {
        return self::$container->get('chamilo.settings.manager');
    }

    /**
     * @return \Chamilo\CourseBundle\Manager\SettingsManager
     */
    public static function getCourseSettingsManager()
    {
        return self::$container->get('chamilo_course.settings.manager');
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public static function getEntityManager()
    {
        return \Database::getManager();
    }

    /**
     * @return \Sonata\UserBundle\Entity\UserManager
     */
    public static function getUserManager()
    {
        //return self::$container->get('sonata.user.user_manager');
        return self::$container->get('fos_user.user_manager');
    }

    /**
     * @return \Sonata\UserBundle\Entity\GroupManager
     */
    public static function getGroupManager()
    {
        return self::$container->get('fos_user.group_manager');
    }

    /**
     * @return \Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher
     */
    public static function getEventDispatcher()
    {
        return self::$container->get('event_dispatcher');
    }

    /**
     * @return \Symfony\Component\Form\FormFactory
     */
    public static function getFormFactory()
    {
        return self::$container->get('form.factory');
    }


    /**
     * @param string $message
     * @param string $type error|success|warning|danger
     */
    public static function addFlash($message, $type = 'success')
    {
        $session = self::getSession();
        $session->getFlashBag()->add($type, $message);
    }

    /**
     * @return \Symfony\Cmf\Component\Routing\ChainRouter
     */
    public static function getRouter()
    {
        return self::$container->get('router');
    }
}
