   ALTER TABLE /*_*/periodic_related_change
           ADD wc_namespace int unsigned NOT NULL;
   ALTER TABLE /*_*/periodic_related_change
           ADD wc_title varchar(255) binary NOT NULL;
        UPDATE /*_*/periodic_related_change
          JOIN /*_*/page ON wc_page=page_id
           SET wc_title=page_title,
               wc_namespace=page_namespace;
   ALTER TABLE /*_*/periodic_related_change
      DROP KEY /*i*/wc_user_page;
   ALTER TABLE /*_*/periodic_related_change
      DROP KEY /*i*/wc_page;
   ALTER TABLE /*_*/periodic_related_change
          DROP wc_page;
   ALTER TABLE /*_*/periodic_related_change
ADD UNIQUE KEY /*i*/wc_user_page (wc_user, wc_namespace, wc_title);
   ALTER TABLE /*_*/periodic_related_change
       ADD KEY /*i*/wc_page (wc_namespace, wc_title);
