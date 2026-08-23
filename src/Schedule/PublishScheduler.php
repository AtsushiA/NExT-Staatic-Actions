<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Schedule;

use DateTimeImmutable;
use Exception;
use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;

final class PublishScheduler {

	public const HOOK = 'next_staatic_actions_scheduled_publish';

	/** @var Settings */
	private $settings;

	/** @var DebugLogger */
	private $logger;

	public function __construct( Settings $settings, DebugLogger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function registerHooks(): void {
		add_action( self::HOOK, array( $this, 'runScheduledPublish' ) );
		add_action( 'update_option_' . Settings::OPTION_NAME, array( $this, 'onSettingsSaved' ) );
		add_action( 'add_option_' . Settings::OPTION_NAME, array( $this, 'onSettingsSaved' ) );
		add_action( 'init', array( $this, 'ensureScheduled' ) );
	}

	public function onSettingsSaved(): void {
		$this->reschedule( $this->settings->get() );
	}

	public function ensureScheduled(): void {
		$settings = $this->settings->get();
		if ( ! $settings['schedule_enabled'] ) {
			return;
		}
		if ( wp_next_scheduled( self::HOOK ) === false ) {
			$this->reschedule( $settings );
		}
	}

	public function reschedule( array $settings ): void {
		wp_clear_scheduled_hook( self::HOOK );

		if ( ! $settings['schedule_enabled'] || ! defined( 'STAATIC_VERSION' ) ) {
			return;
		}

		if ( $settings['schedule_mode'] === 'one_time' ) {
			$timestamp = $this->timestampForOneTime( $settings['schedule_date'], $settings['schedule_time'] );
			if ( $timestamp === null ) {
				$this->logger->log( 'Scheduled publish not (re)scheduled: one-time date/time is empty or in the past.' );

				return;
			}
			wp_schedule_single_event( $timestamp, self::HOOK );
			$this->logger->log( sprintf( 'Scheduled a one-time publish for %s.', gmdate( 'Y-m-d H:i:s', $timestamp ) ) );

			return;
		}

		$timestamp = $this->nextDailyOccurrence( $settings['schedule_time'] );
		wp_schedule_event( $timestamp, 'daily', self::HOOK );
		$this->logger->log( sprintf( 'Scheduled a %s publish, next run %s.', $settings['schedule_mode'], gmdate( 'Y-m-d H:i:s', $timestamp ) ) );
	}

	public function runScheduledPublish(): void {
		$settings = $this->settings->get();
		if ( ! $settings['schedule_enabled'] || ! defined( 'STAATIC_VERSION' ) ) {
			return;
		}

		if ( $settings['schedule_mode'] === 'weekly' ) {
			$today = strtolower( ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'D' ) );
			if ( ! in_array( $today, $settings['schedule_weekdays'], true ) ) {
				return;
			}
		}

		$this->logger->log( 'Scheduled publish triggered.' );
		do_action( 'staatic_publish' );
	}

	private function nextDailyOccurrence( string $time ): int {
		[$hour, $minute] = array_map( 'intval', explode( ':', $time ) );
		$timezone        = wp_timezone();
		$now             = new DateTimeImmutable( 'now', $timezone );
		$next            = $now->setTime( $hour, $minute, 0 );
		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	private function timestampForOneTime( string $date, string $time ): ?int {
		if ( $date === '' ) {
			return null;
		}

		try {
			$target = new DateTimeImmutable( "{$date} {$time}", wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}

		$now = new DateTimeImmutable( 'now', wp_timezone() );
		if ( $target <= $now ) {
			return null;
		}

		return $target->getTimestamp();
	}
}
