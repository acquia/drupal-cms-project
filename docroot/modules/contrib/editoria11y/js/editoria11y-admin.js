/**
 * Drupal initializer.
 * Launch as behavior and pull variables from config.
 */

Drupal.behaviors.editoria11yAdmin = {
    attach: function (context, settings) {

        if (context === document && CSS.supports("selector(:is(body))")) {

            // Look for resetters.
            let resetPath = document.querySelector('.ed11y-reset-this-path');
            let resetDismissal = document.querySelectorAll('.ed11y-reset-this-dismissal');

            if (drupalSettings.editoria11y.api_url && ((drupalSettings.editoria11y.admin && !!resetPath) || resetDismissal.length > 0)) {
                // Set up error handling.
                const messages = new Drupal.Message();
                function handleErrors(data) {
                    console.error("Reset failed.");
                    messages.add(`${data.message}: ${data.description}`, {type: 'warning'});
                    return;
                }

                let apiUrl = drupalSettings.editoria11y.api_url;
                let sessionUrl = drupalSettings.editoria11y.session_url;

                // Get cross-request token for API
                let csrfToken = false;
                let getCsrfToken = async function (data, action) {
                    {
                        fetch(`${sessionUrl}`, {
                            method: "GET"
                        })
                            .then(res => res.text())
                            .then(token => {
                                csrfToken = token;
                                postData(data, action).catch((error) => {
                                    console.log(error);
                                })
                            })
                            .catch((error) => {
                                console.log(error);
                            })
                    }
                }

                let hidables = [];

                // Send a request to the purge API endpoint.
                let postData = async function (data, action) {
                    if (!csrfToken) {
                        await getCsrfToken(data, action);
                    }
                    if (csrfToken) {
                        let apiRoot = apiUrl.replace('results/report', 'purge');
                        let url = `${apiRoot}/${action}`;
                        fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrfToken,
                            },
                            body: JSON.stringify(data),
                        })
                            .catch((error) => {
                                console.log(error);
                            })
                            .then((response) => response.json())
                            .then((data) => {
                                if (data.message === 'error') {
                                    handleErrors(data);
                                } else {
                                    messages.add('Deleted.', {type: 'status'});
                                };
                            })
                    }
                }

                // Responsive tables: drupal.org/project/editoria11y/issues/3536720.
                const deSaw = (el) => {
                  const inner = el.querySelector('.tablesaw-cell-content');
                  return inner ? inner.textContent.trim() : el.textContent.trim();
                }

                if (!!resetPath) {
                    let purgeThisPage = function (event) {
                        event.preventDefault;
                        let data = {
                            page_path: deSaw( event.target.querySelector('.ed11y-api-path') )
                        };
                        hidables = document.querySelectorAll('.view-editoria11y-results td');
                        hidables?.forEach(el => {
                          el.parentElement.removeChild(el);
                        })
                        postData(data, 'page', hidables).then();
                    }
                    resetPath.removeAttribute('hidden');
                    resetPath.querySelector('a')?.addEventListener('click', purgeThisPage);
                } else if (!!resetDismissal) {
                    let purgeThisDismissal = async function (event) {
                        event.preventDefault;
                        let tr = event.target.closest('tr');
                        let pagePath = deSaw( event.target.querySelector('.ed11y-api-path') );
                        let resultName = deSaw( tr.querySelector('.ed11y-api-result-name') );
                        let marked = deSaw( tr.querySelector('.ed11y-api-marked') );
                        let by = deSaw ( event.target.querySelector('.ed11y-api-by') );
                        let data = {
                            page_path: pagePath,
                            result_name: resultName,
                            marked: marked,
                            by: by
                        };
                        let previous = tr.previousElementSibling;
                        postData(data, 'dismissal', hidables).then();
                        previous?.querySelector('a')?.focus();
                        window.setTimeout((tr)=> {
                          tr.parentElement.removeChild( tr );
                        }, 0, tr);
                    }
                    resetDismissal.forEach(el => { el.addEventListener('click', purgeThisDismissal) });
                }
            }
        }
    }
};
