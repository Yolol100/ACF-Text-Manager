<?php
namespace WA_ACF_PTM\Admin;

use WA_ACF_PTM\Admin\Services\Cache_Invalidation_Service;
use WA_ACF_PTM\Admin\Services\Field_Value_Service;
use WA_ACF_PTM\Admin\Services\File_Upload_Service;
use WA_ACF_PTM\Admin\Services\Import_Plan_Builder;
use WA_ACF_PTM\Admin\Services\Import_Processor;
use WA_ACF_PTM\Admin\Services\Special_Field_Service;
use WA_ACF_PTM\Admin\Services\Target_Permission_Service;
use WA_ACF_PTM\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Module {
	private static bool $registered = false;
	private Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	public function register(): void {
		if ( self::$registered || ! is_admin() ) {
			return;
		}

		self::$registered = true;
		$this->bind_services();
		$this->container->get( Admin_Page::class )->register();
		$this->container->get( Admin_Actions::class )->register();
	}

	private function bind_services(): void {
		$this->container->singleton( Field_Value_Service::class, static fn () => new Field_Value_Service() );
		$this->container->singleton( Special_Field_Service::class, fn ( Container $container ) => new Special_Field_Service( $container->get( Field_Value_Service::class ) ) );
		$this->container->singleton( Page_Repository::class, fn ( Container $container ) => new Page_Repository( $container->get( Field_Value_Service::class ), $container->get( Special_Field_Service::class ) ) );
		$this->container->singleton( Template_Renderer::class, static fn () => new Template_Renderer() );
		$this->container->singleton( Admin_Page_View_Model::class, fn ( Container $container ) => new Admin_Page_View_Model( $container->get( Page_Repository::class ) ) );
		$this->container->singleton( CSV_Service::class, static fn () => new CSV_Service() );
		$this->container->singleton( Import_Plan_Store::class, static fn () => new Import_Plan_Store() );
		$this->container->singleton( File_Upload_Service::class, static fn () => new File_Upload_Service() );
		$this->container->singleton( Target_Permission_Service::class, static fn () => new Target_Permission_Service() );
		$this->container->singleton( Cache_Invalidation_Service::class, fn ( Container $container ) => new Cache_Invalidation_Service( $container->get( Page_Repository::class ) ) );
		$this->container->singleton( Import_Plan_Builder::class, fn ( Container $container ) => new Import_Plan_Builder( $container->get( CSV_Service::class ), $container->get( Field_Value_Service::class ), $container->get( Special_Field_Service::class ) ) );
		$this->container->singleton( Import_Processor::class, fn ( Container $container ) => new Import_Processor( $container->get( Import_Plan_Store::class ), $container->get( Page_Repository::class ), $container->get( Field_Value_Service::class ), $container->get( Special_Field_Service::class ), $container->get( Target_Permission_Service::class ) ) );
		$this->container->singleton( Admin_Page::class, fn ( Container $container ) => new Admin_Page( $container->get( Admin_Page_View_Model::class ), $container->get( Template_Renderer::class ) ) );
		$this->container->singleton( Admin_Actions::class, fn ( Container $container ) => new Admin_Actions( $container->get( Page_Repository::class ), $container->get( CSV_Service::class ), $container->get( Import_Plan_Store::class ), $container->get( File_Upload_Service::class ), $container->get( Import_Plan_Builder::class ), $container->get( Import_Processor::class ), $container->get( Cache_Invalidation_Service::class ), $container->get( Target_Permission_Service::class ) ) );
	}
}
