jQuery(document).ready(function($) {
	if (
		typeof elementor === 'undefined' ||
		!elementor.modules ||
		!elementor.modules.layouts ||
		!elementor.modules.layouts.panel ||
		!elementor.modules.layouts.panel.pages ||
		!elementor.modules.layouts.panel.pages.menu ||
		!elementor.modules.layouts.panel.pages.menu.Menu ||
		typeof llms_elementor === 'undefined' ||
		!llms_elementor.builder_url
	) {
		return;
	}

	elementor.modules.layouts.panel.pages.menu.Menu.addItem({
		name: 'vibelms-course-builder',
		title: 'Открыть конструктор курса VibeLMS',
		icon: 'wp-menu-image dashicons-before dashicons-welcome-learn-more',
		callback: function callback() {
			var builderWindow = window.open(llms_elementor.builder_url, '_blank', 'noopener,noreferrer');
			if (!builderWindow) {
				window.location.href = llms_elementor.builder_url;
			}
		}
	}, 'navigate_from_page', 'finder');
});
