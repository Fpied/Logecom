<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260724094533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY `FK_C53D045FF347EFB`');
        $this->addSql('ALTER TABLE image CHANGE main_image main_image TINYINT DEFAULT 0 NOT NULL, CHANGE order_image order_image INT DEFAULT 0 NOT NULL, CHANGE produit_id produit_id INT NOT NULL');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045FF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD nom VARCHAR(100) NOT NULL, ADD prenom VARCHAR(100) NOT NULL, ADD email VARCHAR(180) NOT NULL, ADD adresse_postale VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045FF347EFB');
        $this->addSql('ALTER TABLE image CHANGE main_image main_image TINYINT NOT NULL, CHANGE order_image order_image INT NOT NULL, CHANGE produit_id produit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT `FK_C53D045FF347EFB` FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE message DROP nom, DROP prenom, DROP email, DROP adresse_postale');
    }
}
