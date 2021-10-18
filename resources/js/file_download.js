(function ($, Drupal, drupalSettings) {

  function downloadFile(url) {
    // Create a link and set the URL using `createObjectURL`
    const link = document.createElement("a");
    link.style.display = "none";
    link.href = url;
    url_params = url.split('/');
    file_name = url_params[url_params.length - 1]
    link.download = file_name;

    // It needs to be added to the DOM so it can be clicked
    document.body.appendChild(link);
    link.click();

    // To make this work on Firefox we need to wait
    // a little while before removing it.
    setTimeout(() => {
      URL.revokeObjectURL(link.href), 60;
      link.parentNode.removeChild(link);
    }, 0);

    // Push download event to datalayer for GTM tracking
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      "event": "whitepaper_download",
      "whitepaper_url": url,
      "whitepaper_filename": file_name,
    });
  }
  $(document).ready(function (e) {
    var url = drupalSettings.whitepaper_file_path;
    if (url !=null) {
      setTimeout(function(){
        downloadFile(url);
       // window.open(url, '_blank');
      }, 0);
    }
  });
})(jQuery, Drupal, drupalSettings);
