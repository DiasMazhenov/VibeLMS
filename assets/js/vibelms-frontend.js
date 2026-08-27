(function () {
	'use strict';

	document.querySelectorAll('[data-vibelms-slider]').forEach(function (slider) {
		var track = slider.querySelector('[data-vibelms-slider-track]');
		var slides = slider.querySelectorAll('[data-vibelms-slide]');
		var dots = slider.querySelectorAll('[data-vibelms-slider-dot]');
		var previous = slider.querySelector('[data-vibelms-slider-prev]');
		var next = slider.querySelector('[data-vibelms-slider-next]');
		if (!track || slides.length < 2) return;
		var index = 0;
		function update(nextIndex) {
			index = Math.max(0, Math.min(nextIndex, slides.length - 1));
			track.scrollTo({ left: slides[index].offsetLeft, behavior: 'smooth' });
			dots.forEach(function (dot, dotIndex) {
				dot.classList.toggle('is-active', dotIndex === index);
				if (dotIndex === index) dot.setAttribute('aria-current', 'true'); else dot.removeAttribute('aria-current');
			});
			previous.disabled = index === 0;
			next.disabled = index === slides.length - 1;
		}
		previous.addEventListener('click', function () { update(index - 1); });
		next.addEventListener('click', function () { update(index + 1); });
		dots.forEach(function (dot) { dot.addEventListener('click', function () { update(parseInt(dot.getAttribute('data-vibelms-slider-dot'), 10) || 0); }); });
		track.addEventListener('scroll', function () {
			var closest = 0;
			Array.prototype.forEach.call(slides, function (slide, slideIndex) {
				if (Math.abs(track.scrollLeft - slide.offsetLeft) < Math.abs(track.scrollLeft - slides[closest].offsetLeft)) closest = slideIndex;
			});
			if (closest !== index) update(closest);
		});
		update(0);
	});
}());
