CREATE TABLE tx_newslettersubscription_domain_model_subscription (
    uid int(11) unsigned NOT NULL AUTO_INCREMENT,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(3) unsigned DEFAULT '0' NOT NULL,
    hidden tinyint(3) unsigned DEFAULT '0' NOT NULL,
    
    email varchar(255) DEFAULT '' NOT NULL,
    unsubscribe_token varchar(255) DEFAULT '' NOT NULL,
    
    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY email (email),
    KEY unsubscribe_token (unsubscribe_token)
);