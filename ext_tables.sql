CREATE TABLE tx_mpcrss_domain_model_feed (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    
    tt_content int(11) unsigned DEFAULT '0' NOT NULL,
    
    title varchar(255) DEFAULT '' NOT NULL,
    feed_url text NOT NULL,
    source_name varchar(100) DEFAULT '' NOT NULL,
    description text,
    
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
    
    sorting int(11) DEFAULT '0' NOT NULL,
    
    sys_language_uid int(11) DEFAULT '0' NOT NULL,
    l10n_parent int(11) DEFAULT '0' NOT NULL,
    l10n_source int(11) DEFAULT '0' NOT NULL,
    l10n_diffsource mediumblob,
    
    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY tt_content (tt_content),
    KEY language (l10n_parent,sys_language_uid)
);

#
# Table structure for table 'tt_content'
#
CREATE TABLE tt_content (
    tx_mpcrss_feeds int(11) unsigned DEFAULT '0' NOT NULL
);

