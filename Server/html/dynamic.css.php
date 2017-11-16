<?php header('Content-type: text/css'); ?>

@media (min-width: 768px){ .container{ width: 90% !important; } }
@media print{
	a[href]:after{ content: "" !important; }
	.container{ width: 98% !important; }
}

table a, .table a { text-decoration: none !important; }

#children-tabs li a{ display: block !important; }

.hidden{ visibility: hidden !important; }

iframe{ border: none; overflow: auto; }

.tab-content{ padding: 10px 20px; border: 1px solid #DDDDDD; border-top: none; }

#pc-loading{ background: none repeat scroll 0 0 yellow; font-family: arial; left: 10px; margin-top: -10px; opacity: 0.85; position: absolute; top: 20px; width: 150px; }

.navbar a.btn { margin-left: 10px; margin-right: 10px; }

.view-on-click a.btn { max-width: 75px; }

/* prevent prototype conflicts */
li.dropdown{ display: block !important; }

.hspacer-xs{ margin-left: 0.1em; margin-right: 0.1em; }
.hspacer-sm{ margin-left: 0.2em; margin-right: 0.2em; }
.hspacer-md{ margin-left: 0.4em; margin-right: 0.4em; }
.hspacer-lg{ margin-left: 0.8em; margin-right: 0.8em; }
.vspacer-xs{ margin-top: 0.1em; margin-bottom: 0.1em; }
.vspacer-sm{ margin-top: 0.2em; margin-bottom: 0.2em; }
.vspacer-md{ margin-top: 0.4em; margin-bottom: 0.4em; }
.vspacer-lg{ margin-top: 0.8em; margin-bottom: 0.8em; }

div.datePicker{ font-size: 1.3em; }
.always_shown{ display: inline !important; }
.text-bold{ font-weight: bold; }
.text-italic{ font-style: italic; }

.form-control{ width: 90% !important; }
.input-group .form-control{ width: 100% !important; }
.panel .btn{ overflow: hidden; }

.select2-container .select2-choice{ height: 2.4em; line-height: 2.2em; }
.select2-container .select2-choice .select2-arrow b{ background-position: 0 -0.1em; }

.navbar ul.dropdown-menu{ max-height: 400px; overflow-y: auto; }

.search_highlight{ background-color: #ff8c00; color: #ffffff; }

.date_combo { padding-right: 0 !important; }
.date_combo select { width: 100% !important; padding-left: 0; padding-right: 0; }

img[src="blank.gif"] { max-height: 10px !important; }

.computers-blueskyid{ white-space: normal !important; max-width: 85px !important; min-width: 85px !important; overflow: hidden;  }
.computers-notify{ white-space: normal !important; max-width: 50px !important; min-width: 50px !important; overflow: hidden;  }
.computers-alert{ white-space: normal !important; max-width: 50px !important; min-width: 50px !important; overflow: hidden;  }

/* fixes for glyph icons in some themes */
.glyphicon-camera:before { content: "\e046"; }
.glyphicon-lock:before { content: "\e033"; }
.glyphicon-eur:before { content: "\20ac"; }
.glyphicon-calendar:before { content: "\e109"; }
.glyphicon-bell:before { content: "\e123"; }
.glyphicon-wrench:before { content: "\e136"; }
.glyphicon-briefcase:before { content: "\e139"; }

/* compact theme styles */
body{ font-size: 12px; }

.btn {
	font-size: 12px;
	padding: 4px 10px;
}

.btn-lg, .btn-group-lg > .btn {
	font-size: 15px;
	padding: 6px 15px;
}

.form-group {
	margin-bottom: 8px;
}

.form-control {
	font-size: 12px;
	height: auto;
	padding: 4px 6px;
}

.input-sm {
	border-radius: 3px;
	font-size: 12px;
	padding: 2px 6px;
}

select.input-sm {
	height: 25px;
	line-height: 25px;
}

.dropdown-menu {
	font-size: 12px;
}

.table > thead > tr > th, .table > tbody > tr > th, .table > tfoot > tr > th, .table > thead > tr > td, .table > tbody > tr > td, .table > tfoot > tr > td {
	padding: 4px;
}

h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
	line-height: 2;
}

h1, .h1 {
	font-size: 27px;
}

h2, .h2 {
	font-size: 24px;
}

h3, .h3 {
	font-size: 20px;
}

h4, .h4 {
	font-size: 16px;
}

.navbar {
	margin-bottom: 13px;
	min-height: 40px;
}

.navbar-fixed-bottom {
	margin-bottom: 0 !important;
}

.navbar-brand {
	font-size: 15px;
	height: 40px;
	padding: 12px;
}

.navbar-nav > li > a {
	padding-bottom: 9px;
	padding-top: 9px;
	line-height: 26px;
}

.navbar-text {
	margin-bottom: 12px;
	margin-top: 14px;
}

.navbar-right {
	margin-right: 0;
}

.navbar-btn {
	margin-bottom: 9px;
	margin-top: 9px;
}

.page-header {
	margin: 20px 0 10px;
	padding-bottom: 0;
}

.navbar-nav > li > a { margin-top: 0; margin-bottom: 0; }

.panel-heading {
	padding: 6px;
}

.panel-title {
	font-size: 14px;
}

