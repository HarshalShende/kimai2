/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiReloadPageWidget: a simple helper to reload the page on events
 */

import jQuery from "jquery";

export default class KimaiReloadPageWidget {

    constructor(events, fullReload) {
        const self = this;

        const reloadPage = function (event) {
            if (fullReload) {
                document.location.reload();
            } else {
                self.loadPage(document.location);
            }
        };

        for (const eventName of events.split(' ')) {
            document.addEventListener(eventName, reloadPage);
        }
    }
    
    static create(events, fullReload) {
        if (fullReload === undefined || fullReload === null) {
            fullReload = false;
        }
        return new KimaiReloadPageWidget(events, fullReload);
    }
    
    _showOverlay() {
        document.dispatchEvent(new CustomEvent('kimai.reloadContent', {detail: 'div.page-wrapper'}));
    }

    _hideOverlay() {
        document.dispatchEvent(new Event('kimai.reloadedContent'));
    }
    
    loadPage(url) {
        const self = this;
        
        self._showOverlay();

        jQuery.ajax({
            url: url,
            data: {},
            success: function (response) {
                jQuery('section.content').replaceWith(
                    jQuery(response).find('section.content')
                );
                document.dispatchEvent(new Event('kimai.reloadPage'));
                self._hideOverlay();
            },
            dataType: 'html',
            error: function(jqXHR, textStatus, errorThrown) {
                self._hideOverlay();
                document.location = url;
            }
        });        
    }

}
