(function() {
    'use strict';

    if (typeof window.__PROXY_CONFIG__ === 'undefined') {
        console.error('Proxy config not found! Make sure config script is loaded before proxy-rewriter.js');
        return;
    }

    var config = window.__PROXY_CONFIG__;

    /**
     * @param {string} url
     * @return {boolean}
     */
    function isTargetDomain(url) {
        if (typeof url !== 'string') return false;
        if (url.startsWith('/') && !url.startsWith('//')) return true;

        if (url.startsWith('//')) {
            url = 'https:' + url;
        }

        for (var i = 0; i < config.targetDomains.length; i++) {
            var domain = config.targetDomains[i].replace(/\./g, '\\.');
            var domainPattern = new RegExp('https?://((?:[^/]+\\.)*)' + domain, 'i');
            if (domainPattern.test(url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param {string} url
     * @return {string}
     */
    function rewriteUrl(url) {
        if (typeof url !== 'string') return url;
        if (url.startsWith(config.proxyBase)) return url;

        if (url.startsWith('/' + config.subdomainMarker + '/')) {
            return config.proxyBase + url;
        }

        if (url.startsWith('/') && !url.startsWith('//')) {
            return config.proxyBase + config.subdomainPrefix + url;
        }

        var originalUrl = url;
        if (url.startsWith('//')) {
            url = 'https:' + url;
        }

        if (isTargetDomain(originalUrl)) {
            var urlObj;
            try {
                urlObj = new URL(url);
            } catch (e) {
                return url;
            }

            var host = urlObj.hostname;

            var isMain = config.mainDomains.some(function(md) {
                return host === md || host === 'www.' + md;
            });

            if (isMain) {
                return config.proxyBase + urlObj.pathname + urlObj.search + urlObj.hash;
            } else {
                return config.proxyBase + '/' + config.subdomainMarker + '/' + host + urlObj.pathname + urlObj.search + urlObj.hash;
            }
        }
        return url;
    }

    var TM_REGEX = /\b([a-zA-Z]{6})\b(?!\u2122)/g;
    var SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'PRE', 'TEXTAREA', 'INPUT', 'SELECT', 'KBD']);

    var processingQueue = [];
    var processingScheduled = false;

    /**
     * @param {Node} node
     * @return {void}
     */
    function processTextNode(node) {
        if (!node.parentNode || SKIP_TAGS.has(node.parentNode.tagName)) return;

        var text = node.nodeValue;
        if (!text || text.length < 6) return;

        if (TM_REGEX.test(text)) {
            var newText = text.replace(TM_REGEX, '$1\u2122');
            if (newText !== text) {
                node.nodeValue = newText;
            }
        }
    }

    /**
     * @return {void}
     */
    function processQueue() {
        var nodesToProcess = processingQueue.slice(0, 100);
        processingQueue = processingQueue.slice(100);

        nodesToProcess.forEach(function(node) {
            if (node.nodeType === Node.ELEMENT_NODE) {
                walkAndProcess(node);
            } else if (node.nodeType === Node.TEXT_NODE) {
                processTextNode(node);
            }
        });

        if (processingQueue.length > 0) {
            requestAnimationFrame(processQueue);
        } else {
            processingScheduled = false;
        }
    }

    /**
     * @param {Node} node
     * @return {void}
     */
    function scheduleProcessing(node) {
        processingQueue.push(node);
        if (!processingScheduled) {
            processingScheduled = true;
            requestAnimationFrame(processQueue);
        }
    }

    /**
     * @param {Node} root
     * @return {void}
     */
    function walkAndProcess(root) {
        var walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        var node;
        var count = 0;
        var maxNodes = 1000;

        while ((node = walker.nextNode()) && count < maxNodes) {
            processTextNode(node);
            count++;
        }
    }

    /**
     * @return {void}
     */
    function init() {
        console.log('Proxy rewriter initialized with config:', config);

        if (document.body) {
            walkAndProcess(document.body);
        }

        var observerConfig = {
            childList: true,
            subtree: true,
            characterData: true
        };

        var observer = new MutationObserver(function(mutations) {
            var batchSize = 0;
            mutations.forEach(function(mutation) {
                if (batchSize > 50) return;

                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (batchSize < 50) {
                            scheduleProcessing(node);
                            batchSize++;
                        }
                    });
                } else if (mutation.type === 'characterData') {
                    if (batchSize < 50) {
                        scheduleProcessing(mutation.target);
                        batchSize++;
                    }
                }
            });
        });

        if (document.body) {
            observer.observe(document.body, observerConfig);
        }

        document.addEventListener('click', function(e) {
            var target = e.target.closest('a');
            if (target && target.href) {
                var rewritten = rewriteUrl(target.href);
                if (rewritten !== target.href) {
                    target.href = rewritten;
                }
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            if (typeof url === 'string') {
                url = rewriteUrl(url);
            } else if (url instanceof Request) {
                url = new Request(rewriteUrl(url.url), url);
            }
            return originalFetch.call(this, url, options);
        };
    }

    if (window.XMLHttpRequest) {
        var originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function(method, url) {
            if (typeof url === 'string') {
                arguments[1] = rewriteUrl(url);
            }
            return originalOpen.apply(this, arguments);
        };
    }

    if (window.history) {
        var originalPushState = history.pushState;
        history.pushState = function(state, title, url) {
            if (url) url = rewriteUrl(url);
            return originalPushState.apply(this, [state, title, url]);
        };

        var originalReplaceState = history.replaceState;
        history.replaceState = function(state, title, url) {
            if (url) url = rewriteUrl(url);
            return originalReplaceState.apply(this, [state, title, url]);
        };
    }

    function patchDomProperties() {
        const propertiesToPatch = [
            { cls: HTMLImageElement, props: ['src', 'srcset'] },
            { cls: HTMLScriptElement, props: ['src'] },
            { cls: HTMLLinkElement, props: ['href'] },
            { cls: HTMLAnchorElement, props: ['href'] },
            { cls: HTMLSourceElement, props: ['src', 'srcset'] },
            // Для SVG image элементов
            { cls: SVGImageElement, props: ['href'] }
        ];

        propertiesToPatch.forEach(function(item) {
            if (typeof item.cls === 'undefined') return;

            item.props.forEach(function(propName) {
                var descriptor = Object.getOwnPropertyDescriptor(item.cls.prototype, propName);

                // Если дескриптор не найден в самом прототипе, ищем выше по цепочке (для SVG часто нужно)
                if (!descriptor) {
                    var proto = Object.getPrototypeOf(item.cls.prototype);
                    while (proto && !descriptor) {
                        descriptor = Object.getOwnPropertyDescriptor(proto, propName);
                        proto = Object.getPrototypeOf(proto);
                    }
                }

                if (descriptor && descriptor.set) {
                    Object.defineProperty(item.cls.prototype, propName, {
                        configurable: true,
                        enumerable: true,
                        get: descriptor.get,
                        set: function(value) {
                            // Игнорируем data:uri и blob:
                            if (typeof value === 'string' &&
                                !value.startsWith('data:') &&
                                !value.startsWith('blob:')
                            ) {
                                // Если это srcset, нужно разбить строку, переписать каждый URL и собрать обратно
                                if (propName === 'srcset') {
                                    value = value.split(',').map(function(part) {
                                        var trimmed = part.trim();
                                        var spacePos = trimmed.lastIndexOf(' ');
                                        if (spacePos === -1) {
                                            return rewriteUrl(trimmed);
                                        }
                                        var url = trimmed.substring(0, spacePos);
                                        var descriptor = trimmed.substring(spacePos);
                                        return rewriteUrl(url) + descriptor;
                                    }).join(', ');
                                } else {
                                    // Обычный src или href
                                    value = rewriteUrl(value);
                                }
                            }
                            descriptor.set.call(this, value);
                        }
                    });
                }
            });
        });

        // Отдельно перехватываем setAttribute, так как некоторые фреймворки используют его
        var originalSetAttribute = Element.prototype.setAttribute;
        Element.prototype.setAttribute = function(name, value) {
            var n = name.toLowerCase();
            if ((n === 'src' || n === 'href' || n === 'srcset') && typeof value === 'string') {
                if (!value.startsWith('data:') && !value.startsWith('blob:')) {
                    // Логика для srcset
                    if (n === 'srcset') {
                        value = value.split(',').map(function(part) {
                            var trimmed = part.trim();
                            var spacePos = trimmed.lastIndexOf(' ');
                            if (spacePos === -1) return rewriteUrl(trimmed);
                            return rewriteUrl(trimmed.substring(0, spacePos)) + trimmed.substring(spacePos);
                        }).join(', ');
                    } else {
                        value = rewriteUrl(value);
                    }
                }
            }
            return originalSetAttribute.call(this, name, value);
        };
    }

    // Запускаем патчинг
    patchDomProperties();

})();