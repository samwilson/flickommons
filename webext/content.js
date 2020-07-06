(function() {
    const flickrDashUrl = 'https://flickrdash.toolforge.org';

    function getId( anchor ) {
        if ( anchor === undefined ) {
            anchor = document.location;
        }
        return anchor.href.match(/[0-9]+/)[0];
    }

    function addSearchResultsLink() {
        let ids = [];
        document.querySelectorAll('.photo-list-view a.title').forEach(anchor => {
            ids.push( getId( anchor ) );
        } );

        const searchResultsHeader = document.querySelector('.search-results-header');
        if (ids.length > 0 && searchResultsHeader) {
            let flickrDashLink = document.querySelector('#flickrdash-dupes') || document.createElement('a');
            flickrDashLink.href = flickrDashUrl + '/flickr/dupes/' + ids.join('/');
            flickrDashLink.text = 'Compare in FlickrDash';
            flickrDashLink.classList.add('view-more-link');
            flickrDashLink.target = '_blank';
            flickrDashLink.title = 'Opens in new tab';
            flickrDashLink.id = 'flickrdash-dupes';
            console.log('Adding FlickrDash link');
            searchResultsHeader.append(flickrDashLink);
        }
    }

    function addShareLink() {
        const id = getId(),
            buttons = document.querySelector( '.photo-engagement-view' ),
            linkWrap = document.createElement( 'div' ),
            link = document.createElement( 'a' );
        // Prevent duplicate links being added.
        if ( document.querySelector( '#flickrdash-share-link' ) ) {
            return;
        }
        // Check that there's somewhere to add the link.
        if ( buttons === null ) {
            return;
        }
        // Construct link.
        link.id = 'flickrdash-share-link';
        link.href = flickrDashUrl + '/edit/' + id;
        link.textContent = 'FD';
        link.title = 'Open in FlickrDash';
        link.classList.add( [ 'button' ] );
        link.style.fontWeight = 'bolder';
        link.style.color = 'white';
        // Add link to page.
        console.info( '[FlickrDash] Adding share link.' );
        linkWrap.append( link );
        buttons.append( linkWrap );
    }

    function main() {
        if (window.flickrDashHasRun) {
            return;
        }
        window.flickrDashHasRun = true;
        console.info('[FlickrDash] Extension loaded.');

        var observer = new MutationObserver(mutations => {
            mutations.forEach( mutation => {
                mutation.addedNodes.forEach(e => {
                    addShareLink();
                    if ( e.classList.contains('photo-list-photo-interaction') ) {
                        addSearchResultsLink();
                    }
                } );
            } );
        } );
        var observerOptions = { childList: true, attributes: true, subtree: true };
        observer.observe(document.body, observerOptions);
    }

    main();
})();
