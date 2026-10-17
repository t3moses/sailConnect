<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Initial Schema Migration
 *
 * Creates the complete Sail Friday database schema including:
 * - boats, crews, events tables (core entities)
 * - boat_availability, crew_availability (registration)
 * - boat_history, crew_history (participation tracking)
 * - crew_whitelist (boat preferences)
 * - season_config (singleton configuration)
 * - flotillas (generated assignments)
 *
 * Converted from: database/migrations/001_initial_schema.sql
 */
final class InitialSchema extends AbstractMigration
{
    /**
     * Create all tables and indexes for Sail Friday database
     */
    public function change(): void
    {
        // ====================================================================
        // Table: boats
        // Stores boat information including owner details, berth capacity,
        // and ranking data
        // ====================================================================
        $boats = $this->table('boats');
        $boats->addColumn('key', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('display_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('owner_first_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('owner_last_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('owner_email', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('owner_mobile', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('min_berths', 'integer', ['default' => 1, 'null' => false])
              ->addColumn('max_berths', 'integer', ['default' => 1, 'null' => false])
              ->addColumn('assistance_required', 'string', ['limit' => 3, 'default' => 'No'])
              ->addColumn('social_preference', 'string', ['limit' => 3, 'default' => 'No'])
              ->addColumn('rank_flexibility', 'integer', ['default' => 1])
              ->addColumn('rank_absence', 'integer', ['default' => 0])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['key'], ['unique' => true, 'name' => 'idx_boats_key'])
              ->addIndex(['owner_first_name', 'owner_last_name'], ['name' => 'idx_boats_owner'])
              ->create();

        // ====================================================================
        // Table: crews
        // Stores crew member information including skills, preferences,
        // and ranking data
        // ====================================================================
        $crews = $this->table('crews');
        $crews->addColumn('key', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('display_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('first_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('last_name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('partner_key', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('mobile', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('social_preference', 'string', ['limit' => 3, 'default' => 'No'])
              ->addColumn('membership_number', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('skill', 'integer', ['default' => 0])
              ->addColumn('experience', 'text', ['null' => true])
              ->addColumn('rank_commitment', 'integer', ['default' => 0])
              ->addColumn('rank_flexibility', 'integer', ['default' => 1])
              ->addColumn('rank_membership', 'integer', ['default' => 0])
              ->addColumn('rank_absence', 'integer', ['default' => 0])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['key'], ['unique' => true, 'name' => 'idx_crews_key'])
              ->addIndex(['first_name', 'last_name'], ['name' => 'idx_crews_name'])
              ->addIndex(['partner_key'], ['name' => 'idx_crews_partner'])
              ->create();

        // ====================================================================
        // Table: events
        // Stores event schedule information
        // ====================================================================
        $events = $this->table('events');
        $events->addColumn('event_id', 'string', ['limit' => 255, 'null' => false])
               ->addColumn('event_date', 'date', ['null' => false])
               ->addColumn('start_time', 'time', ['null' => false])
               ->addColumn('finish_time', 'time', ['null' => false])
               ->addColumn('status', 'string', ['limit' => 20, 'default' => 'upcoming'])
               ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
               ->addIndex(['event_id'], ['unique' => true, 'name' => 'idx_events_event_id'])
               ->addIndex(['event_date'], ['name' => 'idx_events_date'])
               ->create();

        // ====================================================================
        // Table: boat_availability
        // Tracks how many berths each boat offers for each event
        // ====================================================================
        $boatAvailability = $this->table('boat_availability');
        $boatAvailability->addColumn('boat_id', 'integer', ['null' => false])
                        ->addColumn('event_id', 'string', ['limit' => 255, 'null' => false])
                        ->addColumn('berths', 'integer', ['default' => 0, 'null' => false])
                        ->addForeignKey('boat_id', 'boats', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                        ->addForeignKey('event_id', 'events', 'event_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                        ->addIndex(['boat_id', 'event_id'], ['unique' => true])
                        ->addIndex(['boat_id'], ['name' => 'idx_boat_availability_boat'])
                        ->addIndex(['event_id'], ['name' => 'idx_boat_availability_event'])
                        ->create();

        // ====================================================================
        // Table: crew_availability
        // Tracks crew availability status for each event
        // status: 0=UNAVAILABLE, 1=AVAILABLE, 2=GUARANTEED, 3=WITHDRAWN
        // ====================================================================
        $crewAvailability = $this->table('crew_availability');
        $crewAvailability->addColumn('crew_id', 'integer', ['null' => false])
                        ->addColumn('event_id', 'string', ['limit' => 255, 'null' => false])
                        ->addColumn('status', 'integer', ['default' => 0])
                        ->addForeignKey('crew_id', 'crews', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                        ->addForeignKey('event_id', 'events', 'event_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                        ->addIndex(['crew_id', 'event_id'], ['unique' => true])
                        ->addIndex(['crew_id'], ['name' => 'idx_crew_availability_crew'])
                        ->addIndex(['event_id'], ['name' => 'idx_crew_availability_event'])
                        ->addIndex(['status'], ['name' => 'idx_crew_availability_status'])
                        ->create();

        // ====================================================================
        // Table: boat_history
        // Tracks boat participation history for each event
        // participated: 'Y' or '' (empty string)
        // ====================================================================
        $boatHistory = $this->table('boat_history');
        $boatHistory->addColumn('boat_id', 'integer', ['null' => false])
                   ->addColumn('event_id', 'string', ['limit' => 255, 'null' => false])
                   ->addColumn('participated', 'string', ['limit' => 1, 'default' => ''])
                   ->addForeignKey('boat_id', 'boats', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                   ->addForeignKey('event_id', 'events', 'event_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                   ->addIndex(['boat_id', 'event_id'], ['unique' => true])
                   ->addIndex(['boat_id'], ['name' => 'idx_boat_history_boat'])
                   ->addIndex(['event_id'], ['name' => 'idx_boat_history_event'])
                   ->create();

        // ====================================================================
        // Table: crew_history
        // Tracks which boat each crew member was assigned to for each event
        // boat_key: empty string if not assigned, otherwise the boat's key
        // ====================================================================
        $crewHistory = $this->table('crew_history');
        $crewHistory->addColumn('crew_id', 'integer', ['null' => false])
                   ->addColumn('event_id', 'string', ['limit' => 255, 'null' => false])
                   ->addColumn('boat_key', 'string', ['limit' => 255, 'default' => ''])
                   ->addForeignKey('crew_id', 'crews', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                   ->addForeignKey('event_id', 'events', 'event_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                   ->addIndex(['crew_id', 'event_id'], ['unique' => true])
                   ->addIndex(['crew_id'], ['name' => 'idx_crew_history_crew'])
                   ->addIndex(['event_id'], ['name' => 'idx_crew_history_event'])
                   ->addIndex(['boat_key'], ['name' => 'idx_crew_history_boat'])
                   ->create();

        // ====================================================================
        // Table: crew_whitelist
        // Stores crew preferences for specific boats (whitelisted boats)
        // ====================================================================
        $crewWhitelist = $this->table('crew_whitelist');
        $crewWhitelist->addColumn('crew_id', 'integer', ['null' => false])
                     ->addColumn('boat_key', 'string', ['limit' => 255, 'null' => false])
                     ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                     ->addForeignKey('crew_id', 'crews', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                     ->addIndex(['crew_id', 'boat_key'], ['unique' => true])
                     ->addIndex(['crew_id'], ['name' => 'idx_crew_whitelist_crew'])
                     ->addIndex(['boat_key'], ['name' => 'idx_crew_whitelist_boat'])
                     ->create();

        // ====================================================================
        // Table: season_config
        // Stores season-wide configuration (singleton table with id=1)
        // source: 'simulated' or 'production' (time source for testing vs production)
        // ====================================================================
        $seasonConfig = $this->table('season_config', ['id' => false, 'primary_key' => 'id']);
        $seasonConfig->addColumn('id', 'integer', ['null' => false])
                    ->addColumn('year', 'integer', ['null' => false])
                    ->addColumn('source', 'string', ['limit' => 20, 'default' => 'production'])
                    ->addColumn('simulated_date', 'date', ['null' => true])
                    ->addColumn('start_time', 'time', ['null' => false])
                    ->addColumn('finish_time', 'time', ['null' => false])
                    ->addColumn('blackout_from', 'time', ['null' => false])
                    ->addColumn('blackout_to', 'time', ['null' => false])
                    ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                    ->create();

        // Insert default season configuration
        $this->execute("
            INSERT INTO season_config (id, year, source, start_time, finish_time, blackout_from, blackout_to)
            VALUES (1, 2026, 'production', '12:45:00', '17:00:00', '10:00:00', '18:00:00')
        ");

        // ====================================================================
        // Table: flotillas
        // Stores generated flotilla assignments for each event (serialized JSON)
        // ====================================================================
        $flotillas = $this->table('flotillas');
        $flotillas->addColumn('event_id', 'string', ['limit' => 255, 'null' => false])
                 ->addColumn('flotilla_data', 'text', ['null' => false])
                 ->addColumn('generated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                 ->addForeignKey('event_id', 'events', 'event_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                 ->addIndex(['event_id'], ['unique' => true, 'name' => 'idx_flotillas_event'])
                 ->create();

        // ====================================================================
        // Triggers for updated_at timestamp maintenance
        // ====================================================================
        $this->execute("
            CREATE TRIGGER IF NOT EXISTS boats_updated_at
            AFTER UPDATE ON boats
            BEGIN
                UPDATE boats SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END
        ");

        $this->execute("
            CREATE TRIGGER IF NOT EXISTS crews_updated_at
            AFTER UPDATE ON crews
            BEGIN
                UPDATE crews SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END
        ");

        $this->execute("
            CREATE TRIGGER IF NOT EXISTS season_config_updated_at
            AFTER UPDATE ON season_config
            BEGIN
                UPDATE season_config SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END
        ");
    }
}
