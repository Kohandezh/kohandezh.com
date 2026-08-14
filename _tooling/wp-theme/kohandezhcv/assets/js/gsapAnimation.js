gsap.registerPlugin(ScrollTrigger);

(function ($) {
    "use strict";
    // DOM Ready

    // Persian is the default reading experience. Keep its content immediately
    // available instead of hiding complete sections behind scroll-triggered
    // reveals; this also prevents blank gaps on short and mobile viewports.
    const isPersianPage = document.documentElement.lang.toLowerCase().startsWith("fa");
    const revealPersianContent = (selector) => {
        if (!isPersianPage) return false;
        document.querySelectorAll(selector).forEach((el) => {
            el.style.setProperty("opacity", "1", "important");
            el.style.setProperty("visibility", "visible", "important");
            el.style.setProperty("transform", "none", "important");
            el.style.setProperty("filter", "none", "important");
        });
        return true;
    };

    /*========== Start - Scroll Text Animation ==========*/
    /* Animation Text
    ---------------------------------------------------------- */
    var animation_text = function () {
        if (revealPersianContent(".split-text")) return;
        if ($(".split-text").length > 0) {
            var st = $(".split-text");
            if (st.length === 0) return;
            gsap.registerPlugin(SplitText, ScrollTrigger);
            st.each(function (index, el) {
                const $el = $(el);
                const $target = $el.find("p, a").length > 0 ? $el.find("p, a")[0] : el;
                const hasClass = $el.hasClass.bind($el);
                const pxl_split = new SplitText($target, {
                    type: "words, chars",
                    lineThreshold: 0.5,
                    linesClass: "split-line",
                });
                let split_type_set = pxl_split.chars;
                gsap.set($target, { opacity: 1, perspective: 400 });

                const settings = {
                    scrollTrigger: {
                        trigger: $target,
                        start: "top 86%",
                        toggleActions: "play none none none",
                    },
                    // opacity: 0,
                    duration: 0.9,
                    stagger: 0.02,
                    ease: "power3.out",
                };

                if (hasClass("effect-fade")) settings.opacity = 0;

                if (hasClass("split-lines-transform") || hasClass("split-lines-rotation-x")) {
                    pxl_split.split({
                        type: "lines",
                        lineThreshold: 0.5,
                        linesClass: "split-line",
                    });
                    split_type_set = pxl_split.lines;
                    settings.opacity = 0;
                    settings.stagger = 0.5;
                    if (hasClass("split-lines-rotation-x")) {
                        settings.rotationX = -120;
                        settings.transformOrigin = "top center -50";
                    } else {
                        settings.yPercent = 100;
                        settings.autoAlpha = 0;
                    }
                }

                if (hasClass("split-words-scale")) {
                    pxl_split.split({ type: "words" });
                    split_type_set = pxl_split.words;
                    split_type_set.forEach((elw, index) => {
                        gsap.set(
                            elw,
                            {
                                opacity: 0,
                                scale: index % 2 === 0 ? 0 : 2,
                                force3D: true,
                                duration: 0.1,
                                ease: "power3.out",
                                stagger: 0.02,
                            },
                            index * 0.01
                        );
                    });
                    gsap.to(split_type_set, {
                        scrollTrigger: {
                            trigger: el,
                            start: "top 86%",
                        },
                        rotateX: "0",
                        scale: 1,
                        opacity: 1,
                    });
                } else if (hasClass("effect-blur-fade")) {
                    /* WORDS, not lines. The "lines" split rendered every word
                     * as its own display:block div — the hero read
                     * "I'm / building / AI / …" one word per line, and on
                     * Arabic every headline collapsed into a one-word-wide
                     * column (the line detector groups words by measured
                     * offsetTop, and at split time that measurement put each
                     * word on its own line). Words are inline-block, so the
                     * text flows and wraps exactly like the un-split markup —
                     * the layout cannot differ from the authored one, in
                     * either direction. Stagger is per word now, so it is
                     * shortened to keep the whole reveal under ~a second. */
                    pxl_split.split({ type: "words" });
                    split_type_set = pxl_split.words;
                    gsap.fromTo(
                        split_type_set,
                        { opacity: 0, filter: "blur(10px)", y: 20 },
                        {
                            opacity: 1,
                            filter: "blur(0px)",
                            y: 0,
                            duration: 1,
                            stagger: 0.04,
                            ease: "power3.out",
                            scrollTrigger: {
                                trigger: $target,
                                start: "top 86%",
                                toggleActions: "play none none none",
                            },
                        }
                    );
                } else {
                    gsap.from(split_type_set, settings);
                }
            });
        }
    };

    /* Scroll Effect
    ---------------------------------------------------------- */
    var scrolling_effect = function () {
        if (revealPersianContent(".scrolling-effect")) return;
        if ($(".scrolling-effect").length > 0) {
            var st = $(".scrolling-effect");
            st.each(function (index, el) {
                var $el = $(el);
                var delay = parseFloat($el.data("delay")) || 0;
                var settings = {
                    scrollTrigger: {
                        trigger: el,
                        scrub: 3,
                        once: true,
                        toggleActions: "play none none none",
                        start: "30px bottom",
                        end: "bottom bottom",
                        delay: delay,
                    },
                    duration: 0.8,
                    ease: "power3.out",
                };

                if ($el.hasClass("effectRight")) {
                    settings.opacity = 0;
                    settings.x = "80";
                }
                if ($el.hasClass("effectLeft")) {
                    settings.opacity = 0;
                    settings.x = "-80";
                }
                if ($el.hasClass("effectBottom")) {
                    settings.opacity = 0;
                    settings.y = "100";
                }
                if ($el.hasClass("effectTop")) {
                    settings.opacity = 0;
                    settings.y = "-80";
                }
                if ($el.hasClass("effectZoomIn")) {
                    settings.opacity = 0;
                    settings.scale = 0.4;
                }

                gsap.from(el, settings);
            });
        }
    };
    /*========== End - Scroll Text Animation ==========*/

    /*========== Start - Scroll Orther Animation ==========*/
    /* Flip Animation
    ---------------------------------------------------------- */
    var gsapA2 = () => {
        if ($(".gsap-anime-2").length) {
            const cards = document.querySelectorAll(".flip-image");

            function animate() {
                // const isMobile = window.innerWidth < 767;
                // const cardW = isMobile ? 150 : 325;
                // const cardH = isMobile ? 150 : 325;

                const isMobile = window.innerWidth < 575;
                const cardW = 150;
                const cardH = 150;

                const parent = cards[0].parentElement;
                // parent.style.position = "relative";
                const centerX = parent.clientWidth / 2 - cardW / 2;
                const centerY = parent.clientHeight / 2 - cardH / 2;

                cards.forEach((card, i) => {
                    card.style.position = "absolute";
                    card.style.zIndex = i + 1;
                });

                const tl = gsap.timeline({
                    defaults: { ease: "power3.out" },
                    scrollTrigger: {
                        trigger: ".gsap-anime-2",
                        start: "top 80%",
                        toggleActions: "play none none reverse",
                        // markers: true,
                    },
                });

                tl.to(cards, {
                    x: centerX,
                    y: centerY,
                    opacity: 1,
                    duration: 1,
                    stagger: 0.1,
                }).to(cards, {
                    x: (i) => {
                        if (i === 0) return centerX - (isMobile ? 150 : 225);
                        if (i === 1) return centerX - (isMobile ? 90 : 135);
                        if (i === 2) return centerX - (isMobile ? 30 : 45);
                        if (i === 3) return centerX + (isMobile ? 30 : 45);
                        if (i === 4) return centerX + (isMobile ? 90 : 135);
                        if (i === 5) return centerX + (isMobile ? 150 : 225);
                        return centerX;
                    },
                    y: (i) => {
                        if (i === 0) return centerY - (isMobile ? 150 : 150);
                        if (i === 1) return centerY - (isMobile ? 90 : 90);
                        if (i === 2) return centerY - (isMobile ? 30 : 30);
                        if (i === 3) return centerY + (isMobile ? 30 : 30);
                        if (i === 4) return centerY + (isMobile ? 90 : 90);
                        if (i === 5) return centerY + (isMobile ? 150 : 150);
                        return centerY;
                    },
                    rotation: -10,
                    rotateX: 4,
                    rotateY: 10,
                    duration: 1,
                    ease: "power2.out",
                    delay: 0.3,
                });
            }

            animate();

            window.addEventListener("resize", () => {
                gsap.killTweensOf(".flip-image");
                animate();
            });
        }
    };
    /* Scroll Effect Fade
    ---------------------------------------------------------- */
    var scrollEffectFade = () => {
        if (revealPersianContent(".effectFade")) return;
        if ($(".effectFade").length) {
            document.querySelectorAll(".effectFade").forEach((el) => {
                let fromVars = { autoAlpha: 0 };
                let toVars = { autoAlpha: 1, duration: 1, ease: "power3.out" };
                let wrapper = null;
                let startPush = "top 96%";
                let revealTrigger = el;
                let delay = el.dataset.delay ? parseFloat(el.dataset.delay) : 0;
                toVars.delay = delay;

                // Technology cards should reveal as one coherent group. Using
                // the shared list as the trigger avoids half-visible cards while
                // a user scrolls, especially on shorter mobile viewports.
                if (el.matches(".section-tech-stack .tech-infor")) {
                    revealTrigger = el.closest(".tech-list") || el;
                    startPush = "top 94%";
                    fromVars.y = 20;
                    toVars.y = 0;
                    toVars.duration = 0.6;
                    toVars.delay = 0;
                }

                if (el.classList.contains("fadeUp") && !el.classList.contains("no-div")) {
                    wrapper = document.createElement("div");
                    wrapper.classList.add("overflow-hidden");
                    el.parentNode.insertBefore(wrapper, el);
                    wrapper.appendChild(el);
                }

                if (el.classList.contains("no-div")) {
                    wrapper = null;
                }
                if (el.classList.contains("fadeUp")) {
                    fromVars.y = 50;
                    toVars.y = 0;
                } else if (el.classList.contains("fadeDown")) {
                    fromVars.y = -50;
                    toVars.y = 0;
                } else if (el.classList.contains("fadeLeft")) {
                    fromVars.x = -50;
                    toVars.x = 0;
                } else if (el.classList.contains("fadeRight")) {
                    fromVars.x = 50;
                    toVars.x = 0;
                } else if (el.classList.contains("fadeRotateX")) {
                    fromVars.rotationX = 45;
                    fromVars.yPercent = 100;
                    fromVars.transformOrigin = "top center -50";
                    toVars.rotationX = 0;
                    toVars.yPercent = 0;
                    toVars.transformOrigin = "top center -50";
                    toVars.duration = 1;
                    toVars.ease = "power3.out";
                    if (wrapper) {
                        wrapper.style.perspective = "400px";
                    }
                } else if (el.classList.contains("fadeZoom")) {
                    fromVars.scale = 0.8;
                    toVars.scale = 1;
                }

                if (el.matches(".section-tech-stack .tech-infor")) {
                    fromVars.y = 20;
                    toVars.y = 0;
                }

                if (el.classList.contains("view-visible")) {
                    startPush = "top 101%";
                }

                gsap.set(el, fromVars);

                gsap.to(el, {
                    ...toVars,
                    scrollTrigger: {
                        trigger: revealTrigger,
                        start: startPush,
                        toggleActions: "play none none none",
                        // onEnter: () => el.classList.add("animated"),
                        // markers: el.classList.contains("fadeUp"),
                    },
                });
            });
        }
    };
    /* Scroll Line
    ---------------------------------------------------------- */
    var scrollLine = () => {
        if ($(".scroll-down").length) {
            // setup progress line
            gsap.set(".prg-line", { height: "0%" });
            gsap.to(".prg-line", {
                height: "100%",
                duration: 2,
                ease: "none",
                scrollTrigger: {
                    trigger: ".scroll-down",
                    start: "top 40%",
                    end: "bottom 30%",
                    scrub: true,
                    // markers: true,
                },
            });

            // activate timeline items
            const items = document.querySelectorAll(".timeline-item");
            items.forEach((item) => {
                ScrollTrigger.create({
                    trigger: item,
                    start: "top 30%",
                    // end: "bottom 30%",
                    onEnter: () => item.classList.add("active"),
                    onLeaveBack: () => item.classList.remove("active"),
                    // markers: true,
                });
            });
        }
    };
    /* Tech Progress
    ---------------------------------------------------------- */
    var techProgress = () => {
        gsap.utils.toArray(".progress-line").forEach((el) => {
            const progress = el.dataset.progress;

            gsap.fromTo(
                el,
                { width: "15%" },
                {
                    width: progress + "%",
                    duration: 1.5,
                    ease: "power3.out",
                    scrollTrigger: {
                        trigger: el,
                        start: "top 80%",
                        toggleActions: "play none none none",
                    },
                }
            );
        });
    };
    /* Service
    ---------------------------------------------------------- */
    var service = () => {
        gsap.registerPlugin(ScrollTrigger);

        let triggers = [];

        function scrollActive() {
            const sidebar = document.querySelector(".sidebar-user");
            const works = gsap.utils.toArray(".sticky-item");

            if (!sidebar || !works.length) return;

            triggers.forEach((t) => t.kill());
            triggers = [];

            const firstWork = works[0];
            const workList = firstWork.parentElement;
            const wraps = works.map((work) => work.querySelector(".wrap"));

            const clearActiveWork = () => {
                wraps.forEach((wrap) => wrap && wrap.classList.remove("active"));
            };

            const syncActiveWork = () => {
                const focusLine = window.innerHeight * 0.5;
                let activeIndex = 0;

                works.forEach((work, index) => {
                    if (work.getBoundingClientRect().top <= focusLine) {
                        activeIndex = index;
                    }
                });

                wraps.forEach((wrap, index) => {
                    if (wrap) wrap.classList.toggle("active", index === activeIndex);
                });
            };

            const workFlowTrigger = ScrollTrigger.create({
                trigger: workList,
                start: "top 50%",
                endTrigger: workList,
                end: "bottom 50%",
                onEnter: () => {
                    sidebar.classList.add("active");
                    syncActiveWork();
                },
                onUpdate: syncActiveWork,
                onEnterBack: () => {
                    sidebar.classList.add("active");
                    syncActiveWork();
                },
                onLeave: () => {
                    clearActiveWork();
                    sidebar.classList.remove("active");
                },
                onLeaveBack: () => {
                    clearActiveWork();
                    sidebar.classList.remove("active");
                },
                onRefresh: (self) => {
                    if (self.isActive) syncActiveWork();
                    else clearActiveWork();
                },
                invalidateOnRefresh: true,
                // markers: true, //Debug
            });
            triggers.push(workFlowTrigger);

            window.addEventListener("resize", () => {
                ScrollTrigger.refresh();
            });
        }

        scrollActive();
    };
    /* Draw Svg
    ---------------------------------------------------------- */
    var drawSvg = () => {
        if ($(".scribble-wrap").length > 0) {
            const path = document.getElementById("scribblePath");
            const svg = document.querySelector(".scribble");

            const len = path.getTotalLength();
            path.style.setProperty("--len", len);

            const io = new IntersectionObserver(
                ([entry]) => {
                    if (entry.isIntersecting) {
                        svg.classList.add("is-drawn");
                        io.disconnect();
                    }
                },
                { threshold: 0.2 }
            );

            io.observe(svg);
        }
    };
    /*========== End - Scroll Orther Animation ==========*/

    var runAnimations = () => {
        /*-- Scroll Text --*/
        animation_text();
        scrolling_effect();

        /*-- Scroll Orther --*/
        scrollLine();
        techProgress();
        service();
        gsapA2();
        scrollEffectFade();
        drawSvg();
    };
    /*
     * ScrollTrigger start/end pixel offsets for the Work Highlights sticky
     * cards are computed at DOM-ready, before webfonts settle, SplitText
     * re-splits headings, and lazy work images finish loading — all of which
     * change page height afterwards. Without a refresh, each card's cached
     * trigger position drifts out of sync with the real (later) layout,
     * so cards appear to activate at inconsistent, misaligned scroll points.
     * Re-measuring after those async layout shifts keeps every card's
     * trigger aligned to its actual settled position.
     */
    var refreshScrollTriggers = () => {
        var pending = false;
        return () => {
            if (pending) return;
            pending = true;
            requestAnimationFrame(() => {
                ScrollTrigger.refresh();
                pending = false;
            });
        };
    };

    $(function () {
        runAnimations();

        var refresh = refreshScrollTriggers();

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(refresh);
        }

        window.addEventListener("load", refresh);

        document.querySelectorAll(".wg-work img").forEach(function (img) {
            if (img.complete) return;
            img.addEventListener("load", refresh, { once: true });
        });

        /* Expanding or collapsing a service accordion panel changes page height
         * by several hundred pixels. The accordion items themselves carry
         * scrolling-effect triggers, so without re-measuring afterwards every
         * trigger below the panel keeps its stale cached offset and the content
         * visibly jumps while scrolling. Bootstrap fires these events after the
         * height transition finishes, which is exactly when the new layout is
         * settled and safe to measure.
         */
        document.addEventListener("shown.bs.collapse", refresh);
        document.addEventListener("hidden.bs.collapse", refresh);
    });
})(jQuery);
