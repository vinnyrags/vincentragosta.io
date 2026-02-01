import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, CheckboxControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { mode, selectedProjects } = attributes;

    const allProjects = useSelect((select) => {
        return select('core').getEntityRecords('postType', 'project', {
            per_page: -1,
            _embed: true,
            orderby: 'date',
            order: 'desc',
        });
    }, []);

    const onProjectSelectionChange = (isChecked, projectId) => {
        let newSelection = [...selectedProjects];
        if (isChecked) {
            if (newSelection.length < 5) { // Allow up to 5
                newSelection.push(projectId);
            }
        } else {
            newSelection = newSelection.filter((id) => id !== projectId);
        }
        setAttributes({ selectedProjects: newSelection });
    };

    const blockProps = useBlockProps();

    const getDisplayProjects = () => {
        if (!allProjects) return [];
        if (mode === 'curated') {
            return selectedProjects.map(id => allProjects.find(p => p.id === id)).filter(p => p);
        }
        return allProjects.slice(0, 5); // Show up to 5 latest
    };

    const displayProjects = getDisplayProjects();

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Query Settings', 'child-theme')}>
                    <ToggleControl
                        label={__('Display Mode', 'child-theme')}
                        help={mode === 'latest' ? __('Showing latest projects.', 'child-theme') : __('Showing curated projects.', 'child-theme')}
                        checked={mode === 'curated'}
                        onChange={() => setAttributes({ mode: mode === 'latest' ? 'curated' : 'latest', selectedProjects: [] })}
                    />
                    {mode === 'curated' && (
                        <div>
                            <strong>{__('Select up to 5 projects:', 'child-theme')}</strong>
                            {!allProjects && <Spinner />}
                            {allProjects && allProjects.length === 0 && <p>{__('No projects found.', 'child-theme')}</p>}
                            {allProjects && allProjects.map((project) => (
                                <CheckboxControl
                                    key={project.id}
                                    label={project.title.rendered || __('(No title)', 'child-theme')}
                                    checked={selectedProjects.includes(project.id)}
                                    onChange={(isChecked) => onProjectSelectionChange(isChecked, project.id)}
                                    disabled={!selectedProjects.includes(project.id) && selectedProjects.length >= 5}
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
                                const featuredImageUrl = project._embedded?.['wp:featuredmedia']?.[0]?.source_url;
                                const categories = project._embedded?.['wp:term']?.[0];
                                const projectCategory = categories?.find(term => term.taxonomy === 'project_category');

                                return (
                                    <div key={project.id} className="project-card">
                                        <div className="project-card__image-link">
                                            {featuredImageUrl ? (
                                                <img src={featuredImageUrl} alt={project.title.rendered} />
                                            ) : (
                                                <div className="project-card__no-image"></div>
                                            )}
                                        </div>
                                        <div className="project-card__content">
                                            {projectCategory && (
                                                <span className="project-card__category">{projectCategory.name}</span>
                                            )}
                                            <h3 className="project-card__title">
                                                <a>{project.title.rendered || __('(No title)', 'child-theme')}</a>
                                            </h3>
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            <p>{__('No projects to display.', 'child-theme')}</p>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}