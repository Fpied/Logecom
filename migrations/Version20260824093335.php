<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260824093335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_8F91ABF0F347EFB`');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_8F91ABF0FB88E14F`');
        $this->addSql('DROP TABLE avis');
        $this->addSql('ALTER TABLE message ADD telephone VARCHAR(30) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B1DC7A1E940047E5 ON paiement (reference_transaction)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avis (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, note INT NOT NULL, comment LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, date_notice DATETIME NOT NULL, checked TINYINT NOT NULL, utilisateur_id INT DEFAULT NULL, produit_id INT DEFAULT NULL, INDEX IDX_8F91ABF0FB88E14F (utilisateur_id), INDEX IDX_8F91ABF0F347EFB (produit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `FK_8F91ABF0F347EFB` FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `FK_8F91ABF0FB88E14F` FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE message DROP telephone');
        $this->addSql('DROP INDEX UNIQ_B1DC7A1E940047E5 ON paiement');
    }
}
