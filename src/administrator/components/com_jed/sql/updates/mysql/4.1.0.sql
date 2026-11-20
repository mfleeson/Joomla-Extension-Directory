-- PRD-09: organisations own listings (4.1.0).
--
-- Mirrored in install.mysql.utf8.sql. The data migration - one personal organisation per current
-- owner, listings moved to it, maintainers converted without widening anybody's access - is PHP
-- (Organisation\OrganisationMigration), run by script.php::postflight() after this file, because
-- it has to generate random UIDs and unique aliases and decide per maintainer.

CREATE TABLE IF NOT EXISTS `#__jed_organisations`
(
	`id`               int unsigned NOT NULL AUTO_INCREMENT,
	`uid`              varchar(16)  NOT NULL,
	`title`            varchar(255) NOT NULL,
	`alias`            varchar(255) NOT NULL,
	`description`      text,
	`contact_name`     varchar(255) NOT NULL DEFAULT '',
	`contact_email`    varchar(255) NOT NULL DEFAULT '',
	`contact_phone`    varchar(50)  NOT NULL DEFAULT '',
	`website`          varchar(255) NOT NULL DEFAULT '',
	`address`          text,
	`country`          char(2)      NOT NULL DEFAULT '',
	`accept_requests`  tinyint(1)   NOT NULL DEFAULT '1',
	`is_personal`      tinyint(1)   NOT NULL DEFAULT '0',
	`state`            tinyint(1)   NOT NULL DEFAULT '1',
	`primary_user_id`  int unsigned NOT NULL DEFAULT '0',
	`created`          datetime     NOT NULL,
	`created_by`       int unsigned NOT NULL DEFAULT '0',
	`modified`         datetime     DEFAULT NULL,
	`modified_by`      int unsigned DEFAULT NULL,
	`checked_out`      int unsigned DEFAULT NULL,
	`checked_out_time` datetime     DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_jed_organisations_uid` (`uid`),
	UNIQUE KEY `UQ_jed_organisations_alias` (`alias`),
	KEY `IDX_jed_organisations_primary` (`primary_user_id`),
	KEY `IDX_jed_organisations_state` (`state`, `accept_requests`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__jed_organisation_members`
(
	`organisation_id`  int unsigned  NOT NULL,
	`user_id`          int unsigned  NOT NULL,
	`role`             varchar(10)   NOT NULL DEFAULT 'member',
	`state`            tinyint(1)    NOT NULL DEFAULT '0',
	`request_message`  varchar(1000) NOT NULL DEFAULT '',
	`requested_time`   datetime      DEFAULT NULL,
	`invited_by`       int unsigned  DEFAULT NULL,
	`invited_time`     datetime      DEFAULT NULL,
	`decided_by`       int unsigned  DEFAULT NULL,
	`decided_time`     datetime      DEFAULT NULL,
	`joined_time`      datetime      DEFAULT NULL,
	PRIMARY KEY (`organisation_id`, `user_id`),
	KEY `IDX_jed_org_members_user_state` (`user_id`, `state`),
	KEY `IDX_jed_org_members_org_state_role` (`organisation_id`, `state`, `role`),
	CONSTRAINT `FK_jed_org_members_organisation_id` FOREIGN KEY (`organisation_id`) REFERENCES `#__jed_organisations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `#__jed_extensions` ADD COLUMN `organisation_id` int unsigned DEFAULT NULL AFTER `parent_confirmed`;
ALTER TABLE `#__jed_extensions` ADD KEY `IDX_jed_extensions_organisation` (`organisation_id`);
ALTER TABLE `#__jed_extensions` ADD CONSTRAINT `FK_jed_extensions_organisation_id` FOREIGN KEY (`organisation_id`) REFERENCES `#__jed_organisations` (`id`) ON DELETE RESTRICT;

ALTER TABLE `#__jed_extensions_history` ADD COLUMN `organisation_id` int unsigned DEFAULT NULL AFTER `parent_id`;

ALTER TABLE `#__jed_extension_transfers` ADD COLUMN `from_organisation_id` int unsigned DEFAULT NULL AFTER `cancel_reason`;
ALTER TABLE `#__jed_extension_transfers` ADD COLUMN `to_organisation_id` int unsigned DEFAULT NULL AFTER `from_organisation_id`;
