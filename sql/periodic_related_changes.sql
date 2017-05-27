-- Keep track of what has been done when and for who
CREATE TABLE /*_*/periodic_related_change (
  wc_user int unsigned NOT NULL,
  wc_page int unsigned NOT NULL,
  wc_timestamp binary(14) default NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/wc_user_page ON /*_*/periodic_related_change (wc_user, wc_page);
CREATE INDEX /*i*/wc_user ON /*_*/periodic_related_change (wc_user);
CREATE INDEX /*i*/wc_page ON /*_*/periodic_related_change (wc_page);
