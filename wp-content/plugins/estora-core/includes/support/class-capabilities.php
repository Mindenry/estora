<?php
/**
 * Roles and capabilities.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Support;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const VERSION_OPTION = 'estora_caps_version';
	public const VERSION        = '1.0.0';

	public const VIEW_PROPERTIES     = 'estora_view_properties';
	public const EDIT_PROPERTIES     = 'estora_edit_properties';
	public const DELETE_PROPERTIES   = 'estora_delete_properties';
	public const PUBLISH_PROPERTIES  = 'estora_publish_properties';
	public const VIEW_LEADS          = 'estora_view_leads';
	public const VIEW_ALL_LEADS      = 'estora_view_all_leads';
	public const CREATE_LEADS        = 'estora_create_leads';
	public const EDIT_LEADS          = 'estora_edit_leads';
	public const ASSIGN_LEADS        = 'estora_assign_leads';
	public const DELETE_LEADS        = 'estora_delete_leads';
	public const MANAGE_APPOINTMENTS = 'estora_manage_appointments';
	public const VIEW_REPORTS        = 'estora_view_reports';
	public const MANAGE_CMS          = 'estora_manage_cms';
	public const MANAGE_CAMPAIGNS    = 'estora_manage_campaigns';
	public const MANAGE_SETTINGS     = 'estora_manage_settings';
	public const MANAGE_USERS        = 'estora_manage_users';
	public const VIEW_AUDIT          = 'estora_view_audit';

	/**
	 * Every plugin capability.
	 *
	 * @return string[]
	 */
	public static function all_caps(): array {
		return array(
			self::VIEW_PROPERTIES,
			self::EDIT_PROPERTIES,
			self::DELETE_PROPERTIES,
			self::PUBLISH_PROPERTIES,
			self::VIEW_LEADS,
			self::VIEW_ALL_LEADS,
			self::CREATE_LEADS,
			self::EDIT_LEADS,
			self::ASSIGN_LEADS,
			self::DELETE_LEADS,
			self::MANAGE_APPOINTMENTS,
			self::VIEW_REPORTS,
			self::MANAGE_CMS,
			self::MANAGE_CAMPAIGNS,
			self::MANAGE_SETTINGS,
			self::MANAGE_USERS,
			self::VIEW_AUDIT,
		);
	}

	/**
	 * Custom role definitions.
	 *
	 * @return array<string,array{label:string,caps:string[]}>
	 */
	public static function roles(): array {
		return array(
			'estora_property_manager' => array(
				'label' => 'Property Manager',
				'caps'  => array(
					self::VIEW_PROPERTIES,
					self::EDIT_PROPERTIES,
					self::DELETE_PROPERTIES,
					self::PUBLISH_PROPERTIES,
					self::MANAGE_APPOINTMENTS,
					self::VIEW_REPORTS,
				),
			),
			'estora_sales_manager'    => array(
				'label' => 'Sales Manager',
				'caps'  => array(
					self::VIEW_PROPERTIES,
					self::VIEW_LEADS,
					self::VIEW_ALL_LEADS,
					self::CREATE_LEADS,
					self::EDIT_LEADS,
					self::ASSIGN_LEADS,
					self::DELETE_LEADS,
					self::MANAGE_APPOINTMENTS,
					self::VIEW_REPORTS,
				),
			),
			'estora_sales_agent'      => array(
				'label' => 'Sales Agent',
				'caps'  => array(
					self::VIEW_PROPERTIES,
					self::VIEW_LEADS,
					self::CREATE_LEADS,
					self::EDIT_LEADS,
					self::MANAGE_APPOINTMENTS,
				),
			),
			'estora_marketing'        => array(
				'label' => 'Marketing Staff',
				'caps'  => array(
					self::VIEW_PROPERTIES,
					self::VIEW_REPORTS,
					self::MANAGE_CMS,
					self::MANAGE_CAMPAIGNS,
				),
			),
		);
	}

	/**
	 * Install roles and capabilities (idempotent).
	 */
	public static function install(): void {
		foreach ( self::roles() as $slug => $definition ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				add_role( $slug, $definition['label'], array( 'read' => true ) );
				$role = get_role( $slug );
			}
			if ( ! $role ) {
				continue;
			}
			foreach ( self::all_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
			foreach ( $definition['caps'] as $cap ) {
				$role->add_cap( $cap );
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Re-install when the plugin version changes.
	 */
	public static function maybe_install(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Whether the current user can see every lead.
	 */
	public static function can_view_all_leads(): bool {
		return current_user_can( self::VIEW_ALL_LEADS );
	}
}
