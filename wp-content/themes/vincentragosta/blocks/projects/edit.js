import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, CheckboxControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function Edit({ attributes, setAttributes }) {
    const { mode, selectedProjects } = attributes;

    const allProjects = useSelect((select) => {
        return select('core').getEntityRecords('postType', 'project', {
            per_page: -1,
            _embed: true,
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

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Query Settings', 'vincentragosta')}>
                    <ToggleControl
                        label={__('Display Mode', 'vincentragosta')}
                        help={mode === 'latest' ? __('Showing latest projects.', 'vincentragosta') : __('Showing curated projects.', 'vincentragosta')}
                        checked={mode === 'curated'}
                        onChange={() => setAttributes({ mode: mode === 'latest' ? 'curated' : 'latest' })}
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
            <div {...useBlockProps()}>
                <h3>{__('Projects Block', 'vincentragosta')}</h3>
                <p>{__('Mode:', 'vincentragosta')} {mode}</p>
                <p><em>{__('Content is dynamically rendered on the frontend.', 'vincentragosta')}</em></p>
            </div>
        </>
    );
}