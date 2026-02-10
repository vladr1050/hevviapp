<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create sessions table for storing manager authentication sessions
 */
final class Version20260203182650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sessions table for storing manager authentication sessions using Symfony PdoSessionHandler';
    }

    public function up(Schema $schema): void
    {
        // Create sessions table for Symfony PdoSessionHandler
        $this->addSql('
            CREATE TABLE IF NOT EXISTS sessions (
                sess_id VARCHAR(128) NOT NULL PRIMARY KEY,
                sess_data BYTEA NOT NULL,
                sess_lifetime INTEGER NOT NULL,
                sess_time INTEGER NOT NULL
            )
        ');

        // Add index for garbage collection optimization
        $this->addSql('
            CREATE INDEX IF NOT EXISTS sessions_sess_lifetime_idx ON sessions (sess_lifetime)
        ');

        // Add table and column comments
        $this->addSql("COMMENT ON TABLE sessions IS 'Table for storing manager authentication sessions'");
        $this->addSql("COMMENT ON COLUMN sessions.sess_id IS 'Unique session identifier'");
        $this->addSql("COMMENT ON COLUMN sessions.sess_data IS 'Serialized session data'");
        $this->addSql("COMMENT ON COLUMN sessions.sess_lifetime IS 'Session lifetime in seconds'");
        $this->addSql("COMMENT ON COLUMN sessions.sess_time IS 'Unix timestamp of last session update'");
    }

    public function down(Schema $schema): void
    {
        // Drop sessions table
        $this->addSql('DROP TABLE IF EXISTS sessions');
    }
}
