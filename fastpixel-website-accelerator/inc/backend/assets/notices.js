function fastpixelInitNoticeCenter() {
    if (window.fastpixelNoticeCenterInitialized) {
        return;
    }

    window.fastpixelNoticeCenterInitialized = true;

    const config = window.fastpixel_notices || {};
    const titles = config.titles || {};
    const icons = config.icons || {};
    const autoDismissDelay = 5200;
    let runtimeId = 0;

    function ensureCenter() {
        let center = document.getElementById('fastpixel-notification-center');

        if (!center) {
            center = document.createElement('div');
            center.id = 'fastpixel-notification-center';
            center.className = 'fastpixel-notification-center';
            center.setAttribute('aria-live', 'polite');
            center.setAttribute('aria-atomic', 'false');
            document.body.appendChild(center);
        }

        return center;
    }

    function getTypeMeta(type) {
        const normalizedType = ['success', 'warning', 'error', 'notice'].indexOf(type) !== -1 ? type : 'notice';
        let mascot = 'happy';

        if (normalizedType === 'warning' || normalizedType === 'error') {
            mascot = 'sad';
        }

        return {
            type: normalizedType,
            title: titles[normalizedType] || titles.notice || 'FastPixel update',
            icon: icons[mascot] || ''
        };
    }

    function dismissServerNotice(noticeId) {
        if (!noticeId || !config.ajax_url || !config.nonce) {
            return;
        }

        jQuery.ajax({
            type: 'POST',
            url: config.ajax_url,
            data: {
                action: 'fastpixel_dismiss_notice',
                nonce: config.nonce,
                notice_id: noticeId
            }
        });
    }

    function removeNotice(notice, persistDismiss) {
        if (!notice || notice.dataset.fastpixelLeaving === '1') {
            return;
        }

        if (persistDismiss && notice.dataset.fastpixelNoticeId) {
            dismissServerNotice(notice.dataset.fastpixelNoticeId);
        }

        notice.dataset.fastpixelLeaving = '1';
        notice.classList.remove('is-visible');
        notice.classList.add('is-leaving');

        window.setTimeout(function() {
            if (notice.parentNode) {
                notice.parentNode.removeChild(notice);
            }
        }, 260);
    }

    function createNotice(options) {
        const meta = getTypeMeta(options.type);
        const notice = document.createElement('section');
        const avatar = document.createElement('span');
        const avatarImage = document.createElement('img');
        const content = document.createElement('div');
        const appMeta = document.createElement('div');
        const brand = document.createElement('span');
        const brandFast = document.createElement('span');
        const brandPixel = document.createElement('span');
        const title = document.createElement('strong');
        const message = document.createElement('div');
        const close = document.createElement('button');

        notice.className = 'fastpixel-notification fastpixel-notification--' + meta.type;
        notice.setAttribute('role', meta.type === 'error' || meta.type === 'warning' ? 'alert' : 'status');
        notice.dataset.fastpixelNoticeId = options.noticeId || '';
        notice.dataset.fastpixelAutoDismiss = options.autoDismiss ? '1' : '0';

        avatar.className = 'fastpixel-notification__avatar';
        avatarImage.className = 'fastpixel-notification__avatar-image';
        avatarImage.src = meta.icon;
        avatarImage.alt = '';
        avatar.appendChild(avatarImage);
        content.className = 'fastpixel-notification__content';
        appMeta.className = 'fastpixel-notification__app-meta';
        brand.className = 'fastpixel-notification__brand';
        brand.setAttribute('aria-label', config.brand_label || 'FastPixel');
        brandFast.className = 'fastpixel-notification__brand-fast';
        brandFast.textContent = 'FAST';
        brandPixel.className = 'fastpixel-notification__brand-pixel';
        brandPixel.textContent = 'PIXEL';
        title.className = 'fastpixel-notification__title';
        title.textContent = meta.title;
        brand.appendChild(brandFast);
        brand.appendChild(brandPixel);
        appMeta.appendChild(brand);
        appMeta.appendChild(title);

        message.className = 'fastpixel-notification__message';
        message.innerHTML = options.message;

        close.type = 'button';
        close.className = 'fastpixel-notification__close';
        close.setAttribute('aria-label', config.dismiss_label || 'Dismiss notification');
        close.innerHTML = '<span aria-hidden="true">&times;</span>';
        close.addEventListener('click', function() {
            removeNotice(notice, options.persistDismiss);
        });

        content.appendChild(appMeta);
        content.appendChild(message);

        notice.appendChild(avatar);
        notice.appendChild(content);
        notice.appendChild(close);

        if (options.autoDismiss) {
            const progress = document.createElement('div');
            const progressFill = document.createElement('span');

            progress.className = 'fastpixel-notification__progress';
            progressFill.className = 'fastpixel-notification__progress-fill';
            progressFill.style.animationDuration = autoDismissDelay + 'ms';
            progress.appendChild(progressFill);
            notice.appendChild(progress);

            window.setTimeout(function() {
                removeNotice(notice, false);
            }, autoDismissDelay);
        }

        return notice;
    }

    function findExistingNotice(center, noticeId) {
        if (!noticeId) {
            return null;
        }

        return Array.prototype.find.call(center.children, function(child) {
            return child.dataset.fastpixelNoticeId === noticeId;
        }) || null;
    }

    function showNotice(options) {
        const center = ensureCenter();
        const settings = Object.assign({
            type: 'notice',
            message: '',
            noticeId: '',
            persistDismiss: false,
            autoDismiss: true,
            prepend: true
        }, options || {});

        if (!settings.message) {
            return null;
        }

        const existing = findExistingNotice(center, settings.noticeId);
        if (existing) {
            removeNotice(existing, false);
        }

        const notice = createNotice(settings);

        if (settings.prepend && center.firstChild) {
            center.insertBefore(notice, center.firstChild);
        } else {
            center.appendChild(notice);
        }

        window.requestAnimationFrame(function() {
            notice.classList.add('is-visible');
        });

        return notice;
    }

    function getNoticeTypeFromElement(notice) {
        if (!notice || !notice.classList) {
            return 'notice';
        }

        if (notice.classList.contains('notice-success')) {
            return 'success';
        }

        if (notice.classList.contains('notice-warning')) {
            return 'warning';
        }

        if (notice.classList.contains('notice-error')) {
            return 'error';
        }

        return 'notice';
    }

    function normalizeLegacyMessageHtml(html) {
        if (!html) {
            return '';
        }

        return html
            .replace(/^\s*<strong>\s*FastPixel(?: Website Accelerator)?:\s*<\/strong>\s*/i, '')
            .replace(/^\s*[:\-]\s*/i, '')
            .trim();
    }

    function extractLegacyNoticeMessage(source) {
        if (!source) {
            return '';
        }

        const sourceMessage = source.querySelector('.fastpixel-notice-source-message');
        if (sourceMessage && sourceMessage.innerHTML) {
            return normalizeLegacyMessageHtml(sourceMessage.innerHTML);
        }

        const messageContainer = source.querySelector('p') || source;
        const clone = messageContainer.cloneNode(true);
        const strongNodes = clone.querySelectorAll('strong');

        Array.prototype.forEach.call(strongNodes, function(strongNode) {
            const label = (strongNode.textContent || '').trim();

            if (/^FastPixel(?: Website Accelerator)?:$/i.test(label) && strongNode.parentNode) {
                strongNode.parentNode.removeChild(strongNode);
            }
        });

        return normalizeLegacyMessageHtml(clone.innerHTML || '');
    }

    function isLegacyFastPixelNotice(source) {
        if (!source || source.dataset.fastpixelLegacyUpgraded === '1') {
            return false;
        }

        if (source.dataset.fastpixelNoticeSource === '1') {
            return false;
        }

        if (source.closest('#fastpixel-notification-center')) {
            return false;
        }

        if (source.dataset.fastpixelPersistDismiss === '1') {
            return false;
        }

        const firstStrong = source.querySelector('strong');
        if (firstStrong && /^FastPixel(?: Website Accelerator)?:$/i.test((firstStrong.textContent || '').trim())) {
            return true;
        }

        return /^FastPixel(?: Website Accelerator)?:/i.test((source.textContent || '').trim());
    }

    function upgradeServerNotices() {
        const sources = document.querySelectorAll('[data-fastpixel-notice-source="1"]');

        Array.prototype.forEach.call(sources, function(source, index) {
            const message = source.querySelector('.fastpixel-notice-source-message');

            if (!message || !message.innerHTML) {
                return;
            }

            window.setTimeout(function() {
                const popup = showNotice({
                    type: source.dataset.fastpixelNoticeType || 'notice',
                    message: message.innerHTML,
                    noticeId: source.dataset.fastpixelNoticeId || '',
                    persistDismiss: source.dataset.fastpixelPersistDismiss === '1',
                    autoDismiss: source.dataset.fastpixelAutoDismiss === '1',
                    prepend: false
                });

                if (popup && source.parentNode) {
                    source.parentNode.removeChild(source);
                }
            }, index * 100);
        });
    }

    function upgradeLegacyFastPixelNotices() {
        if (!document.querySelector('.fastpixel-website-accelerator-wrap')) {
            return;
        }

        let delayIndex = 0;
        const sources = document.querySelectorAll('.notice');

        Array.prototype.forEach.call(sources, function(source) {
            if (!isLegacyFastPixelNotice(source)) {
                return;
            }

            const message = extractLegacyNoticeMessage(source);
            if (!message) {
                return;
            }

            source.dataset.fastpixelLegacyUpgraded = '1';

            window.setTimeout(function() {
                const popup = showNotice({
                    type: getNoticeTypeFromElement(source),
                    message: message,
                    noticeId: source.dataset.fastpixelNoticeId || ('legacy-' + runtimeId++),
                    persistDismiss: false,
                    autoDismiss: true,
                    prepend: false
                });

                if (popup && source.parentNode) {
                    source.parentNode.removeChild(source);
                }
            }, delayIndex * 100);

            delayIndex++;
        });
    }

    function bindStandardNoticeDismiss() {
        document.addEventListener('click', function(event) {
            const dismissButton = event.target.closest('.notice-dismiss');

            if (!dismissButton) {
                return;
            }

            const notice = dismissButton.closest('.notice[data-fastpixel-persist-dismiss="1"]');

            if (!notice || notice.dataset.fastpixelDismissSent === '1') {
                return;
            }

            notice.dataset.fastpixelDismissSent = '1';
            dismissServerNotice(notice.dataset.fastpixelNoticeId || '');
        });
    }

    window.fastpixelNoticeCenter = {
        show: function(options) {
            const settings = Object.assign({
                noticeId: 'runtime-' + runtimeId++
            }, options || {});

            return showNotice(settings);
        },
        dismiss: function(noticeId) {
            const center = ensureCenter();
            const notice = findExistingNotice(center, noticeId);

            if (notice) {
                removeNotice(notice, false);
            }
        }
    };

    bindStandardNoticeDismiss();
    upgradeServerNotices();
    upgradeLegacyFastPixelNotices();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fastpixelInitNoticeCenter);
} else {
    fastpixelInitNoticeCenter();
}
