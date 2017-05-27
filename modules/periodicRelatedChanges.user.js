$("button.oo-ui-inputWidget-input").click(
	function( event ) {
		var user = $("#mw-input-wpusername input.oo-ui-inputWidget-input").val();
		window.location.href = window.location.href.replace(
			"Special:PeriodicRelatedChanges", "Special:PeriodicRelatedChanges/" + user
		);

		return false;
	} );
