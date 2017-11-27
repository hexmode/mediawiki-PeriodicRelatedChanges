-- Keep track of what has been done when and for who
CREATE TABLE /*_*/periodic_related_change (
  wc_user int unsigned NOT NULL,
  wc_timestamp binary(14) default NULL,
  wc_namespace int unsigned NOT NULL,
  wc_title varchar(255) binary NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/wc_user_page ON /*_*/periodic_related_change (wc_user, wc_namespace, wc_title);
CREATE INDEX /*i*/wc_user ON /*_*/periodic_related_change (wc_user);
CREATE INDEX /*i*/wc_page ON /*_*/periodic_related_change (wc_namespace, wc_title);
