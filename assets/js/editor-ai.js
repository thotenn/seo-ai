/**
 * SEO AI — Gutenberg Inline AI Writing Assistant.
 *
 * Adds an AI button to the block editor toolbar for text manipulation.
 * Actions: Write More, Improve, Summarize, Fix Grammar, Simplify, Add Keywords.
 *
 * @since 0.6.0
 */
(function() {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var PluginDocumentSettingPanel = wp.editPost && wp.editPost.PluginDocumentSettingPanel;
    var registerPlugin = wp.plugins && wp.plugins.registerPlugin;
    var select = wp.data.select;
    var dispatch = wp.data.dispatch;

    if ( ! registerPlugin || ! PluginDocumentSettingPanel ) {
        return;
    }

    var restUrl = ( typeof seoAi !== 'undefined' ) ? seoAi.restUrl : '/wp-json/seo-ai/v1/';
    var nonce   = ( typeof seoAi !== 'undefined' ) ? seoAi.nonce : '';

    /**
     * AI action definitions.
     */
    var AI_ACTIONS = [
        { key: 'write_more',   label: 'Write More',    icon: '✍️', description: 'Continue writing from selected text' },
        { key: 'improve',      label: 'Improve',       icon: '✨', description: 'Rewrite to be clearer and better' },
        { key: 'summarize',    label: 'Summarize',     icon: '📝', description: 'Condense into 1-2 sentences' },
        { key: 'fix_grammar',  label: 'Fix Grammar',   icon: '🔤', description: 'Fix grammar and spelling' },
        { key: 'simplify',     label: 'Simplify',      icon: '💡', description: 'Make easier to understand' },
        { key: 'add_keywords', label: 'Add Keywords',  icon: '🔑', description: 'Naturally include focus keyword' }
    ];

    /**
     * Make an AI inline request.
     */
    function callInlineAI( action, text, context, keyword, customPrompt ) {
        return fetch( restUrl + 'ai/inline', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                action: action,
                text: text,
                context: context || '',
                keyword: keyword || '',
                custom_prompt: customPrompt || ''
            })
        }).then(function( resp ) {
            return resp.json();
        });
    }

    /**
     * Fetch content brief.
     */
    function fetchContentBrief( keyword, postId ) {
        return fetch( restUrl + 'ai/content-brief', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                keyword: keyword,
                post_id: postId || 0
            })
        }).then(function( resp ) {
            return resp.json();
        });
    }

    /**
     * Fetch link suggestions.
     */
    function fetchLinkSuggestions( postId, content, keyword ) {
        return fetch( restUrl + 'ai/link-suggestions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                post_id: postId,
                content: content,
                keyword: keyword || ''
            })
        }).then(function( resp ) {
            return resp.json();
        });
    }

    /**
     * AI Writing Panel — sidebar panel in the block editor.
     */
    function AIWritingPanel() {
        var postMeta = ( typeof seoAiPost !== 'undefined' ) ? seoAiPost.meta : {};
        var postId   = ( typeof seoAiPost !== 'undefined' ) ? seoAiPost.postId : 0;
        var keyword  = postMeta.focus_keyword || '';

        var stateAction = useState( null );
        var loading = stateAction[0];
        var setLoading = stateAction[1];

        var stateResult = useState( '' );
        var result = stateResult[0];
        var setResult = stateResult[1];

        var stateBrief = useState( null );
        var brief = stateBrief[0];
        var setBrief = stateBrief[1];

        var stateBriefLoading = useState( false );
        var briefLoading = stateBriefLoading[0];
        var setBriefLoading = stateBriefLoading[1];

        var stateLinks = useState( null );
        var links = stateLinks[0];
        var setLinks = stateLinks[1];

        var stateLinksLoading = useState( false );
        var linksLoading = stateLinksLoading[0];
        var setLinksLoading = stateLinksLoading[1];

        var stateCustom = useState( '' );
        var customPrompt = stateCustom[0];
        var setCustomPrompt = stateCustom[1];

        function getSelectedText() {
            var sel = window.getSelection();
            return sel ? sel.toString().trim() : '';
        }

        function handleAction( actionKey ) {
            var text = getSelectedText();
            if ( ! text ) {
                setResult( 'Please select some text in the editor first.' );
                return;
            }
            setLoading( actionKey );
            setResult( '' );

            callInlineAI( actionKey, text, '', keyword, '' ).then(function( resp ) {
                setLoading( null );
                if ( resp.success && resp.data && resp.data.text ) {
                    setResult( resp.data.text );
                } else {
                    setResult( 'Error: ' + ( resp.message || 'Unknown error' ) );
                }
            }).catch(function() {
                setLoading( null );
                setResult( 'Error: Request failed. Check your AI provider settings.' );
            });
        }

        function handleCustomAction() {
            var text = getSelectedText();
            if ( ! text ) {
                setResult( 'Please select some text in the editor first.' );
                return;
            }
            if ( ! customPrompt ) {
                setResult( 'Please enter a custom prompt.' );
                return;
            }
            setLoading( 'custom' );
            setResult( '' );

            callInlineAI( 'custom', text, '', keyword, customPrompt ).then(function( resp ) {
                setLoading( null );
                if ( resp.success && resp.data && resp.data.text ) {
                    setResult( resp.data.text );
                } else {
                    setResult( 'Error: ' + ( resp.message || 'Unknown error' ) );
                }
            }).catch(function() {
                setLoading( null );
                setResult( 'Error: Request failed.' );
            });
        }

        function handleContentBrief() {
            if ( ! keyword ) {
                setBrief( { error: 'Set a focus keyword in the SEO AI metabox first.' } );
                return;
            }
            setBriefLoading( true );
            setBrief( null );

            fetchContentBrief( keyword, postId ).then(function( resp ) {
                setBriefLoading( false );
                if ( resp.success && resp.data && resp.data.brief ) {
                    setBrief( resp.data.brief );
                } else {
                    setBrief( { error: resp.message || 'Failed to generate brief.' } );
                }
            }).catch(function() {
                setBriefLoading( false );
                setBrief( { error: 'Request failed.' } );
            });
        }

        function handleLinkSuggestions() {
            var content = '';
            try {
                content = select( 'core/editor' ).getEditedPostContent();
            } catch(e) { /* ignore */ }

            if ( ! content ) {
                setLinks( [{ error: 'No content available.' }] );
                return;
            }
            setLinksLoading( true );
            setLinks( null );

            fetchLinkSuggestions( postId, content, keyword ).then(function( resp ) {
                setLinksLoading( false );
                if ( resp.success && resp.data && resp.data.suggestions ) {
                    setLinks( resp.data.suggestions.length ? resp.data.suggestions : [{ empty: true }] );
                } else {
                    setLinks( [{ error: resp.message || 'Failed to get suggestions.' }] );
                }
            }).catch(function() {
                setLinksLoading( false );
                setLinks( [{ error: 'Request failed.' }] );
            });
        }

        function copyToClipboard( text ) {
            if ( navigator.clipboard ) {
                navigator.clipboard.writeText( text );
            }
        }

        // Build the panel children.
        var children = [];

        // --- AI Actions section ---
        children.push(
            el( 'p', { style: { fontSize: '12px', color: '#6b7280', marginBottom: '8px' } },
                'Select text in the editor, then click an action:'
            )
        );

        AI_ACTIONS.forEach(function( action ) {
            children.push(
                el( 'button', {
                    className: 'button seo-ai-editor-action',
                    style: { display: 'block', width: '100%', textAlign: 'left', marginBottom: '4px', padding: '6px 10px' },
                    disabled: loading === action.key,
                    onClick: function() { handleAction( action.key ); }
                },
                    loading === action.key ? 'Processing...' : ( action.icon + ' ' + action.label )
                )
            );
        });

        // Custom prompt.
        children.push(
            el( 'div', { style: { marginTop: '12px' } },
                el( 'textarea', {
                    rows: 2,
                    style: { width: '100%', marginBottom: '4px', fontSize: '12px' },
                    placeholder: 'Custom instruction...',
                    value: customPrompt,
                    onChange: function(e) { setCustomPrompt( e.target.value ); }
                }),
                el( 'button', {
                    className: 'button',
                    style: { width: '100%' },
                    disabled: loading === 'custom',
                    onClick: handleCustomAction
                }, loading === 'custom' ? 'Processing...' : 'Run Custom Prompt' )
            )
        );

        // Result display.
        if ( result ) {
            children.push(
                el( 'div', { style: { marginTop: '12px', padding: '8px', background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: '4px', fontSize: '13px', whiteSpace: 'pre-wrap' } },
                    el( 'div', { style: { display: 'flex', justifyContent: 'space-between', marginBottom: '4px' } },
                        el( 'strong', null, 'AI Result:' ),
                        el( 'button', {
                            className: 'button-link',
                            style: { fontSize: '11px' },
                            onClick: function() { copyToClipboard( result ); }
                        }, 'Copy' )
                    ),
                    result
                )
            );
        }

        // --- Divider ---
        children.push( el( 'hr', { style: { margin: '16px 0' } } ) );

        // --- Content Brief ---
        children.push(
            el( 'div', null,
                el( 'button', {
                    className: 'button',
                    style: { width: '100%', marginBottom: '8px' },
                    disabled: briefLoading,
                    onClick: handleContentBrief
                }, briefLoading ? 'Generating Brief...' : 'Generate Content Brief' ),
                keyword
                    ? el( 'p', { style: { fontSize: '11px', color: '#6b7280', margin: 0 } }, 'Keyword: ' + keyword )
                    : el( 'p', { style: { fontSize: '11px', color: '#f59e0b', margin: 0 } }, 'Set a focus keyword first' )
            )
        );

        if ( brief ) {
            if ( brief.error ) {
                children.push(
                    el( 'p', { style: { color: '#dc2626', fontSize: '12px' } }, brief.error )
                );
            } else {
                var briefItems = [];
                if ( brief.word_count ) {
                    briefItems.push( el( 'li', null, 'Word count: ' + brief.word_count.min + '-' + brief.word_count.max ) );
                }
                if ( brief.heading_count ) {
                    briefItems.push( el( 'li', null, 'Headings: ' + brief.heading_count.min + '-' + brief.heading_count.max ) );
                }
                if ( brief.link_count ) {
                    briefItems.push( el( 'li', null, 'Internal links: ~' + brief.link_count.internal + ', External: ~' + brief.link_count.external ) );
                }
                if ( brief.search_intent ) {
                    briefItems.push( el( 'li', null, 'Search intent: ' + brief.search_intent ) );
                }
                if ( brief.difficulty ) {
                    briefItems.push( el( 'li', null, 'Difficulty: ' + brief.difficulty ) );
                }
                if ( brief.content_angle ) {
                    briefItems.push( el( 'li', null, 'Angle: ' + brief.content_angle ) );
                }
                children.push( el( 'ul', { style: { fontSize: '12px', paddingLeft: '16px' } }, briefItems ) );

                if ( brief.subtopics && brief.subtopics.length ) {
                    children.push(
                        el( 'p', { style: { fontSize: '12px', fontWeight: '600', marginBottom: '4px' } }, 'Subtopics to cover:' ),
                        el( 'div', { style: { display: 'flex', flexWrap: 'wrap', gap: '4px' } },
                            brief.subtopics.map(function(t, i) {
                                return el( 'span', {
                                    key: i,
                                    style: { background: '#dbeafe', padding: '2px 8px', borderRadius: '12px', fontSize: '11px' }
                                }, t );
                            })
                        )
                    );
                }

                if ( brief.related_keywords && brief.related_keywords.length ) {
                    children.push(
                        el( 'p', { style: { fontSize: '12px', fontWeight: '600', marginBottom: '4px', marginTop: '8px' } }, 'Related keywords:' ),
                        el( 'div', { style: { display: 'flex', flexWrap: 'wrap', gap: '4px' } },
                            brief.related_keywords.map(function(k, i) {
                                return el( 'span', {
                                    key: i,
                                    style: { background: '#dcfce7', padding: '2px 8px', borderRadius: '12px', fontSize: '11px' }
                                }, k );
                            })
                        )
                    );
                }
            }
        }

        // --- Divider ---
        children.push( el( 'hr', { style: { margin: '16px 0' } } ) );

        // --- Link Suggestions ---
        children.push(
            el( 'button', {
                className: 'button',
                style: { width: '100%', marginBottom: '8px' },
                disabled: linksLoading,
                onClick: handleLinkSuggestions
            }, linksLoading ? 'Finding Links...' : 'Suggest Internal Links' )
        );

        if ( links ) {
            if ( links[0] && links[0].error ) {
                children.push( el( 'p', { style: { color: '#dc2626', fontSize: '12px' } }, links[0].error ) );
            } else if ( links[0] && links[0].empty ) {
                children.push( el( 'p', { style: { color: '#6b7280', fontSize: '12px' } }, 'No link suggestions found.' ) );
            } else {
                links.forEach(function( link, i ) {
                    children.push(
                        el( 'div', {
                            key: i,
                            style: { padding: '6px 8px', background: i % 2 === 0 ? '#f9fafb' : '#fff', borderRadius: '4px', marginBottom: '4px', fontSize: '12px' }
                        },
                            el( 'div', { style: { fontWeight: '600' } }, link.target_title || '' ),
                            el( 'div', { style: { color: '#2563eb' } }, 'Anchor: "' + ( link.anchor_text || '' ) + '"' ),
                            link.reason ? el( 'div', { style: { color: '#6b7280', fontStyle: 'italic' } }, link.reason ) : null
                        )
                    );
                });
            }
        }

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'seo-ai-writing-assistant',
                title: 'SEO AI Writing',
                className: 'seo-ai-writing-panel'
            },
            children
        );
    }

    // Register the plugin sidebar panel.
    registerPlugin( 'seo-ai-writing-assistant', {
        render: AIWritingPanel,
        icon: 'admin-generic'
    });

})();
