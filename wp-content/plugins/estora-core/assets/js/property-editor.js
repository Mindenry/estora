/* Property editor gallery control. */
(() => {
  'use strict';
  const field = document.getElementById('estora_gallery');
  if (!field || !window.wp || !wp.media) return;
  const preview = document.querySelector('[data-estora-gallery-preview]');
  const addButton = document.querySelector('[data-estora-gallery-add]');
  if (!preview || !addButton) return;

  const ids = () => field.value.split(',').map((value) => value.trim()).filter(Boolean);
  const write = (list) => { field.value = list.join(','); };

  const render = (attachments) => {
    preview.innerHTML = '';
    attachments.forEach((attachment) => {
      const data = attachment.toJSON ? attachment.toJSON() : attachment;
      const sizes = data.sizes || {};
      const thumb = (sizes.thumbnail && sizes.thumbnail.url) || (sizes.medium && sizes.medium.url) || data.url;
      const figure = document.createElement('figure');
      figure.dataset.id = String(data.id);
      figure.innerHTML = `<img src="${thumb}" alt=""><button type="button" class="button-link" data-remove-image="${data.id}" aria-label="Remove image">&times;</button>`;
      preview.appendChild(figure);
    });
    preview.querySelectorAll('[data-remove-image]').forEach((button) => {
      button.addEventListener('click', () => {
        write(ids().filter((id) => id !== button.dataset.removeImage));
        button.closest('figure').remove();
      });
    });
  };

  addButton.addEventListener('click', () => {
    const frame = wp.media({
      title: 'Select gallery images',
      button: { text: 'Use images' },
      library: { type: 'image' },
      multiple: 'add'
    });
    frame.on('open', () => {
      const selection = frame.state().get('selection');
      ids().forEach((id) => {
        const attachment = wp.media.attachment(id);
        attachment.fetch();
        selection.add(attachment);
      });
    });
    frame.on('select', () => {
      const selection = frame.state().get('selection').toJSON();
      const next = selection.map((attachment) => String(attachment.id));
      write(next);
      render(selection);
    });
    frame.open();
  });
})();
