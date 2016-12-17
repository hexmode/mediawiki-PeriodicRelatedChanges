((nil . ((magit-gerrit-ssh-creds  . "mah@gerrit.wikimedia.org")
		 (mode . flycheck)
		 (mode . company)
		 (mode . edep)
		 (mode . subword)
		 (tab-width . 4)
		 (c-basic-offset . 4)
		 (indent-tabs-mode . t)
		 (c-offsets-alist . ((inexpr-class . +)
							 (inexpr-statement . +)
							 (lambda-intro-cont . +)
										;                                  (inlambda . 0)
							 (template-args-cont c-lineup-template-args +)
							 (incomposition . +)
							 (inmodule . +)
							 (innamespace . +)
							 (inextern-lang . +)
							 (composition-close . 0)
							 (module-close . 0)
							 (namespace-close . 0)
							 (extern-lang-close . 0)
							 (composition-open . 0)
							 (module-open . 0)
							 (namespace-open . 0)
							 (extern-lang-open . 0)
							 (friend . 0)
							 (cpp-define-intro c-lineup-cpp-define +)
							 (cpp-macro-cont . +)
										;                                  (cpp-macro . [0])
							 (inclass . +)
							 (stream-op . c-lineup-streamop)
							 (arglist-cont-nonempty first
													php-lineup-cascaded-calls
													c-lineup-arglist)
							 (comment-intro . 0)
							 (catch-clause . 0)
							 (else-clause . 0)
							 (do-while-closure . 0)
							 (access-label . -)
							 (case-label . 0)
							 (substatement . +)
							 (statement-case-intro . +)
							 (statement . 0)
							 (brace-entry-open . 0)
							 (brace-list-entry . 0)
							 (brace-list-intro . +)
							 (brace-list-close . 0)
							 (block-close . 0)
							 (block-open . 0)
							 (inher-cont . c-lineup-multi-inher)
							 (inher-intro . +)
							 (member-init-cont . c-lineup-multi-inher)
							 (member-init-intro . +)
							 (annotation-var-cont . +)
							 (annotation-top-cont . 0)
							 (topmost-intro . 0)
							 (knr-argdecl . 0)
							 (func-decl-cont . +)
							 (inline-close . 0)
							 (class-close . 0)
							 (defun-block-intro . +)
							 (defun-close . 0)
							 (defun-open . 0)
							 (c . c-lineup-C-comments)
							 (string . c-lineup-dont-change)
										;					 (topmost-intro-cont first php-lineup-cascaded-calls +)
							 (brace-list-open . 0)
										;				 	 (inline-open . 0)
							 (arglist-close . php-lineup-arglist-close)
										;	 				 (arglist-intro . php-lineup-arglist-intro)
										;				 	 (statement-cont first php-lineup-cascaded-calls
										;							 php-lineup-string-cont +)
							 (statement-case-open . 0)
										;					 			 (label . +)
							 (substatement-label . 2)
										;							 	 (substatement-open . 0)
							 (knr-argdecl-intro . +)))
		 (c-hanging-braces-alist
		  (defun-open after)
		  (block-open after)
		  (defun-close))
		 (lice:default-license . "gpl-3.0")
		 (eval . (progn (when (fboundp 'delete-trailing-whitespace)
						  (delete-trailing-whitespace))
                          (tabify (point-min) (point-max))))
		 (flycheck-phpcs-standard . "/home/mah/work/code/mediawiki/codesniffer/MediaWiki")
		 (flycheck-phpmd-rulesets . ("/home/mah/work/code/mediawiki/messdetector/phpmd-ruleset.xml"))
)))
