import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, CheckboxControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import './editor.scss'; // Import editor-specific styles

export default function Edit({ attributes, setAttributes }) {
    const { mode, selectedProjects } = attributes;

    // This hook fetches the project data. The `_embed` parameter is crucial
    // as it includes linked data like the featured image URL.
    const allProjects = useSelect((select) => {
        return select('core').getEntityRecords('postType', 'project', {
            per_page: -1,
            _embed: true, // This includes featured image data
            orderby: 'date',
            order: 'desc',
        });
    }, []);

    const onProjectSelectionChange = (isChecked, projectId) => {
        let newSelection = [...selectedProjects];
        if (isChecked) {
            if (newSelection.length < 3) {
                newSelection.push(projectId);
            }
        } else {
            newSelection = newSelection.filter((id) => id !== projectId);
        }
        setAttributes({ selectedProjects: newSelection });
    };

    const blockProps = useBlockProps();

    // Determine which projects to display in the preview
    const getDisplayProjects = () => {
        if (!allProjects) {
            return [];
        }

        if (mode === 'curated') {
            // Filter allProjects to only include those in selectedProjects,
            // and maintain the order of selection.
            return selectedProjects
                .map(id => allProjects.find(p => p.id === id))
                .filter(p => p); // Filter out any undefined if a project was deleted
        }

        // For 'latest' mode, just return the first 3
        return allProjects.slice(0, 3);
    };

    const displayProjects = getDisplayProjects();

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Query Settings', 'vincentragosta')}>
                    <ToggleControl
                        label={__('Display Mode', 'vincentragosta')}
                        help={mode === 'latest' ? __('Showing latest projects.', 'vincentragosta') : __('Showing curated projects.', 'vincentragosta')}
                        checked={mode === 'curated'}
                        onChange={() => setAttributes({ mode: mode === 'latest' ? 'curated' : 'latest', selectedProjects: [] })}
                    />
                    {mode === 'curated' && (
                        <div>
                            <strong>{__('Select up to 3 projects:', 'vincentragosta')}</strong>
                            {!allProjects && <Spinner />}
                            {allProjects && allProjects.length === 0 && (
                                <p>{__('No projects found. Please create some first.', 'vincentragosta')}</p>
                            )}
                            {allProjects && allProjects.map((project) => (
                                <CheckboxControl
                                    key={project.id}
                                    label={project.title.rendered || __('(No title)', 'vincentragosta')}
                                    checked={selectedProjects.includes(project.id)}
                                    onChange={(isChecked) => onProjectSelectionChange(isChecked, project.id)}
                                    disabled={!selectedProjects.includes(project.id) && selectedProjects.length >= 3}
                                />
                            ))}
                        </div>
                    )}
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {!allProjects ? (
                    <Spinner />
                ) : (
                    <div className="projects-grid">
                        {displayProjects.length > 0 ? (
                            displayProjects.map((project) => {
                                // Safely get the featured image URL from the embedded data
                                const featuredImageUrl = project._embedded?.['wp:featuredmedia']?.[0]?.source_url;

                                return (
                                    <div key={project.id} className="project-card">
                                        {featuredImageUrl ? (
                                            <img src={featuredImageUrl} alt={project.title.rendered} />
                                        ) : (
                                            <div className="project-card__no-image"></div>
                                        )}
                                        <h3 className="project-card__title">
                                            {project.title.rendered || __('(No title)', 'vincentragosta')}
                                        </h3>
                                    </div>
                                );
                            })
                        ) : (
                            <p>{__('No projects to display. Please create projects or adjust query settings.', 'vincentragosta')}</p>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}