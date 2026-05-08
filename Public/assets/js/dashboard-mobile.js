(() => {
  const MOBILE_WIDTH = 980;

  const menuIcon = `
    <span class="dashboard-menu-lines" aria-hidden="true">
      <span></span>
      <span></span>
      <span></span>
    </span>
  `;

  const closeIcon = `
    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none"
      stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 6 6 18"></path>
      <path d="m6 6 12 12"></path>
    </svg>
  `;

  function isMobile() {
    return window.innerWidth <= MOBILE_WIDTH;
  }

  function syncMobileBarHeight() {
    const mobileBar = document.querySelector(".dashboard-mobile-bar");
    if (!mobileBar) return;
    const height = Math.ceil(mobileBar.getBoundingClientRect().height);
    document.documentElement.style.setProperty("--dashboard-mobile-bar-height", `${height}px`);
  }

  function initMobileDashboard() {
    const layout = document.querySelector(".layout");
    const sidebar = document.querySelector(".side");

    if (!layout || !sidebar || document.querySelector(".dashboard-mobile-bar")) return;

    if (!sidebar.id) sidebar.id = "dashboardSidebar";

    const logo = sidebar.querySelector(".brandRow img");
    const mobileBar = document.createElement("header");
    mobileBar.className = "dashboard-mobile-bar";
    mobileBar.innerHTML = `
      <button class="dashboard-menu-button" type="button" aria-label="Open navigation"
        aria-controls="${sidebar.id}" aria-expanded="false">
        ${menuIcon}
      </button>
      <div class="dashboard-mobile-brand">
        ${logo ? `<img src="${logo.getAttribute("src")}" alt="">` : ""}
      </div>
    `;

    const backdrop = document.createElement("button");
    backdrop.className = "dashboard-sidebar-backdrop";
    backdrop.type = "button";
    backdrop.setAttribute("aria-label", "Close navigation");

    const closeButton = document.createElement("button");
    closeButton.className = "sidebar-mobile-close";
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Close navigation");
    closeButton.innerHTML = closeIcon;

    layout.parentNode.insertBefore(mobileBar, layout);
    layout.parentNode.insertBefore(backdrop, layout);
    sidebar.insertBefore(closeButton, sidebar.firstChild);
    syncMobileBarHeight();

    const menuButton = mobileBar.querySelector(".dashboard-menu-button");

    function setSidebar(open) {
      document.body.classList.toggle("dashboard-sidebar-open", open);
      menuButton.setAttribute("aria-expanded", String(open));
      sidebar.setAttribute("aria-hidden", String(isMobile() && !open));
      if (open) {
        closeButton.focus({ preventScroll: true });
      } else {
        menuButton.focus({ preventScroll: true });
      }
    }

    function closeSidebar() {
      if (document.body.classList.contains("dashboard-sidebar-open")) setSidebar(false);
    }

    menuButton.addEventListener("click", () => setSidebar(true));
    closeButton.addEventListener("click", closeSidebar);
    backdrop.addEventListener("click", closeSidebar);

    document.addEventListener("pointerdown", (event) => {
      if (!isMobile() || !document.body.classList.contains("dashboard-sidebar-open")) return;
      if (!sidebar.contains(event.target) && !menuButton.contains(event.target)) closeSidebar();
    });

    sidebar.addEventListener("click", (event) => {
      if (event.target.closest(".nav button, .nav a, .profileCard") && isMobile()) closeSidebar();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeSidebar();
    });

    window.addEventListener("resize", () => {
      syncMobileBarHeight();
      if (!isMobile()) {
        document.body.classList.remove("dashboard-sidebar-open");
        menuButton.setAttribute("aria-expanded", "false");
        sidebar.removeAttribute("aria-hidden");
      } else if (!document.body.classList.contains("dashboard-sidebar-open")) {
        sidebar.setAttribute("aria-hidden", "true");
      }
    });

    if (isMobile()) sidebar.setAttribute("aria-hidden", "true");
  }

  function initMobileMessaging() {
    const panel = document.querySelector(".messaging-panel");
    if (!panel || panel.dataset.mobileMessagingReady === "true") return;
    panel.dataset.mobileMessagingReady = "true";
    let lockedScrollY = 0;

    function lockPageScroll() {
      if (!isMobile() || document.body.dataset.dashboardScrollLocked === "true") return;
      lockedScrollY = window.scrollY || document.documentElement.scrollTop || 0;
      document.body.dataset.dashboardScrollLocked = "true";
      document.body.style.top = `-${lockedScrollY}px`;
    }

    function unlockPageScroll() {
      if (document.body.dataset.dashboardScrollLocked !== "true") return;
      const restoreY = lockedScrollY;
      delete document.body.dataset.dashboardScrollLocked;
      document.body.style.top = "";
      lockedScrollY = 0;
      window.scrollTo(0, restoreY);
    }

    function markMessagingOpen() {
      lockPageScroll();
      document.body.classList.add("dashboard-messaging-open");
      syncMobileBarHeight();
    }

    // Mobile inbox/chat switching — CSS handles visibility via .mobile-chat-active.
    // We only add/remove that class; no MutationObserver needed.

    function restoreInboxElements() {
      const sidebar = panel.querySelector(".conversations-sidebar");
      const list = panel.querySelector(".conversations-list");
      const chatArea = panel.querySelector(".chat-area");

      if (sidebar) sidebar.style.display = "";
      if (list) list.style.display = "block";
      if (chatArea) chatArea.style.display = "";
    }

    function refreshConversationList() {
      if (typeof window.loadConversations !== "function") return;
      Promise.resolve(window.loadConversations()).catch(() => {});
    }

    function showInbox() {
      if (!isMobile()) return;
      panel.classList.remove("mobile-chat-active");
      markMessagingOpen();

      if (typeof window.showConversationsList === "function") {
        try {
          window.showConversationsList();
        } catch (error) {
          restoreInboxElements();
        }
      } else {
        restoreInboxElements();
      }

      restoreInboxElements();
      refreshConversationList();
    }

    function showChat() {
      if (!isMobile()) return;
      panel.classList.add("mobile-chat-active");
      markMessagingOpen();
    }

    const messagesButton = document.getElementById("btnMessages");
    if (messagesButton) {
      messagesButton.addEventListener("click", () => {
        window.setTimeout(() => {
          if (!isMobile() || !panel.classList.contains("open")) return;
          panel.classList.remove("mobile-chat-active");
          markMessagingOpen();
          restoreInboxElements();
        }, 0);
      });
    }

    // Back button → inbox; conversation click → chat
    panel.addEventListener("click", (event) => {
      if (!isMobile()) return;
      if (event.target.closest("#backToConversations")) {
        window.setTimeout(showInbox, 0);
        return;
      }
      if (event.target.closest(".conversation-item")) {
        window.setTimeout(showChat, 0);
      }
    });

    // Clean up mobile state when panel closes (nav buttons call classList.remove("open"))
    // We patch the dashboard's showDashboard/showJobBrowsing etc. via a simple wrapper
    // that runs after the panel class is removed. No observer needed.
    const _origHide = window._hideMessagingMobile;
    window._hideMessagingMobile = function () {
      panel.classList.remove("mobile-chat-active");
      document.body.classList.remove("dashboard-messaging-open");
      unlockPageScroll();
      if (_origHide) _origHide();
    };

    // Also handle resize: reset mobile state on desktop
    window.addEventListener("resize", () => {
      syncMobileBarHeight();
      if (!isMobile()) {
        panel.classList.remove("mobile-chat-active");
        document.body.classList.remove("dashboard-messaging-open");
        unlockPageScroll();
      }
    });

    // Set initial body state if panel is already open (e.g. page restore)
    if (panel.classList.contains("open") && isMobile()) {
      markMessagingOpen();
    }
  }

  function initMobileShell() {
    initMobileDashboard();
    initMobileMessaging();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initMobileShell);
  } else {
    initMobileShell();
  }
})();
