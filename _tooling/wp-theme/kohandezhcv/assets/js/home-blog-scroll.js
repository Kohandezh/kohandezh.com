(function () {
    "use strict";

    var BATCH = 6;

    function stripHtml(html) {
        var div = document.createElement("div");
        div.innerHTML = html;
        return (div.textContent || div.innerText || "").replace(/\s+/g, " ").trim();
    }

    function trimWords(text, count) {
        var words = text.split(" ");
        if (words.length <= count) return text;
        return words.slice(0, count).join(" ") + "…";
    }

    function formatDate(iso) {
        try {
            var lang = document.documentElement.lang || "en";
            return new Intl.DateTimeFormat(lang, { year: "numeric", month: "long", day: "numeric" }).format(new Date(iso));
        } catch (e) {
            return iso;
        }
    }

    function createCard(post, readLabel) {
        var article = document.createElement("article");
        article.className = "blog-local-item";

        var top = document.createElement("div");
        top.className = "blog-local-top";
        var title = document.createElement("h5");
        title.className = "blog-local-title";
        title.textContent = stripHtml(post.title.rendered);
        var date = document.createElement("span");
        date.className = "blog-local-date";
        date.textContent = formatDate(post.modified);
        top.appendChild(title);
        top.appendChild(date);

        var summary = document.createElement("p");
        summary.className = "blog-local-summary";
        summary.textContent = trimWords(stripHtml(post.excerpt.rendered), 28);

        var meta = document.createElement("div");
        meta.className = "blog-local-meta";
        var tag = document.createElement("span");
        tag.className = "blog-local-tag";
        var terms = post._embedded && post._embedded["wp:term"] && post._embedded["wp:term"][0];
        tag.textContent = (terms && terms[0] && terms[0].name) || "";
        var link = document.createElement("a");
        link.className = "blog-local-link";
        link.href = post.link;
        link.setAttribute("aria-label", readLabel + ": " + stripHtml(post.title.rendered));
        link.appendChild(document.createTextNode(readLabel + " "));
        var icon = document.createElement("i");
        icon.className = "icon icon-arrow-right-top";
        icon.setAttribute("aria-hidden", "true");
        link.appendChild(icon);
        meta.appendChild(tag);
        meta.appendChild(link);

        article.appendChild(top);
        article.appendChild(summary);
        article.appendChild(meta);
        return article;
    }

    function init() {
        var list = document.querySelector(".blog-local-list[data-kdcv-blog-feed]");
        var config = window.KDCV_CONFIG || {};
        if (!list || !config.restPostsUrl) return;

        var readLabel = list.dataset.readLabel || "Read original";
        var loaded = parseInt(list.dataset.loaded, 10) || 0;
        var exhausted = false;
        var loading = false;

        var sentinel = document.createElement("div");
        sentinel.className = "blog-local-sentinel";
        sentinel.setAttribute("aria-hidden", "true");
        list.insertAdjacentElement("afterend", sentinel);

        function loadMore() {
            loading = true;
            var url = config.restPostsUrl + "?per_page=" + BATCH + "&offset=" + loaded + "&_embed=wp:term";

            fetch(url, { credentials: "same-origin" })
                .then(function (response) {
                    if (response.status === 400) { exhausted = true; return []; }
                    if (!response.ok) throw new Error("blog feed request failed");
                    return response.json();
                })
                .then(function (posts) {
                    if (!posts.length) {
                        exhausted = true;
                        observer.disconnect();
                        return;
                    }
                    posts.forEach(function (post) {
                        list.appendChild(createCard(post, readLabel));
                    });
                    loaded += posts.length;
                    if (posts.length < BATCH) {
                        exhausted = true;
                        observer.disconnect();
                    }
                })
                .catch(function () {
                    exhausted = true;
                    observer.disconnect();
                })
                .then(function () {
                    loading = false;
                });
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !loading && !exhausted) {
                    loadMore();
                }
            });
        }, { rootMargin: "400px" });

        observer.observe(sentinel);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
        init();
    }
}());
