/* Accessible automatic-activation tabs, scoped to each reusable component. */
(() => {
  document.querySelectorAll('[data-component-tabs]').forEach((component) => {
    const tabs = [...component.querySelectorAll('[role="tab"]')];
    const select = (tab, focus = false) => {
      tabs.forEach((item) => {
        const active = item === tab;
        item.setAttribute('aria-selected', String(active));
        item.tabIndex = active ? 0 : -1;
        item.classList.toggle('pill--active', active);
        document.getElementById(item.getAttribute('aria-controls')).hidden = !active;
      });
      if (focus) tab.focus();
    };
    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => select(tab));
      tab.addEventListener('keydown', (event) => {
        const rtl = getComputedStyle(component).direction === 'rtl';
        let next;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = tabs.length - 1;
        if (event.key === 'ArrowRight') next = index + (rtl ? -1 : 1);
        if (event.key === 'ArrowLeft') next = index + (rtl ? 1 : -1);
        if (next === undefined) return;
        event.preventDefault();
        select(tabs[(next + tabs.length) % tabs.length], true);
      });
    });
  });
})();
