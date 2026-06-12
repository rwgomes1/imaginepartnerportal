document.addEventListener('DOMContentLoaded', () => {
  // Find all collapsible menu-sections
  const collapsibleSections = document.querySelectorAll('.menu-section.collapsible');
  collapsibleSections.forEach(section => {
    // The heading <span> inside the li
    const heading = section.querySelector('span');
    if (heading) {
      heading.addEventListener('click', () => {
        // Toggle the .expanded class on the li
        section.classList.toggle('expanded');
      });
    }
  });
});
