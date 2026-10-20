const MIN_DESCRIPTION_LENGTH = 10;
const MAX_ANSWERS = 3;

const FIELD_CLASS =
    'w-full rounded-md border border-stroke bg-white px-3 py-2 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white';

const STATUS_BASE = 'mt-2 text-sm';
const STATUS_TONES = {
    info: 'text-body-color dark:text-dark-6',
    error: 'text-red-600 dark:text-red-400',
    success: 'text-secondary',
};

const initIncidentAssist = (root) => {
    const form = root.closest('form');
    const description = form?.querySelector('#description');
    const title = form?.querySelector('#title');
    const type = form?.querySelector('#type');
    const csrfToken = form?.querySelector('input[name="_token"]');

    if (!form || !description || !root.dataset.questionsUrl || !root.dataset.composeUrl) {
        return;
    }

    const startButton = root.querySelector('[data-assist-start]');
    const hint = root.querySelector('[data-assist-hint]');
    const panel = root.querySelector('[data-assist-panel]');
    const cancelButton = root.querySelector('[data-assist-cancel]');
    const status = root.querySelector('[data-assist-status]');
    const sourceNote = root.querySelector('[data-assist-source]');
    const questionsBox = root.querySelector('[data-assist-questions]');
    const actions = root.querySelector('[data-assist-actions]');
    const composeButton = root.querySelector('[data-assist-compose]');
    const preview = root.querySelector('[data-assist-preview]');
    const previewText = root.querySelector('[data-assist-preview-text]');
    const modeNote = root.querySelector('[data-assist-mode]');
    const useButton = root.querySelector('[data-assist-use]');
    const keepButton = root.querySelector('[data-assist-keep]');

    if (!startButton || !panel || !questionsBox || !composeButton || !preview || !previewText) {
        return;
    }

    let questions = [];
    let descriptionAtCompose = '';
    let controller = null;
    let busy = false;

    const setStatus = (message, tone = 'info') => {
        status.textContent = message;
        status.className = `${STATUS_BASE} ${STATUS_TONES[tone] ?? STATUS_TONES.info}`;
    };

    const setSource = (message) => {
        sourceNote.textContent = message;
        sourceNote.hidden = message === '';
    };

    const setBusy = (value) => {
        busy = value;
        composeButton.disabled = value;
        updateStartButton();
    };

    const updateStartButton = () => {
        if (!startButton) {
            return;
        }

        const length = description.value.trim().length;
        startButton.disabled = busy || length < MIN_DESCRIPTION_LENGTH;

        if (hint) {
            hint.textContent =
                length < MIN_DESCRIPTION_LENGTH
                    ? `Saisissez au moins ${MIN_DESCRIPTION_LENGTH} caractères pour activer l'aide.`
                    : "L'aide propose des questions de précision, puis une description à valider.";
        }
    };

    const resetPanel = () => {
        questions = [];
        descriptionAtCompose = '';
        questionsBox.replaceChildren();
        preview.hidden = true;
        previewText.value = '';
        modeNote.hidden = true;
        setStatus('');
        setSource('');
    };

    const postJson = async (url, payload) => {
        controller?.abort();
        controller = new AbortController();

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken?.value ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
            signal: controller.signal,
        });

        let data = null;

        try {
            data = await response.json();
        } catch {
            data = null;
        }

        if (!response.ok) {
            if (response.status === 429) {
                throw new Error('Trop de demandes. Patientez une minute avant de réessayer.');
            }

            if (response.status === 422) {
                const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(firstError || 'Vérifiez le titre et la description avant de réessayer.');
            }

            throw new Error("L'aide à la rédaction est momentanément indisponible.");
        }

        return data;
    };

    const renderQuestions = (list) => {
        questions = Array.isArray(list) ? list.slice(0, MAX_ANSWERS) : [];
        questionsBox.replaceChildren();

        questions.forEach((question, index) => {
            const id = String(question?.id || `q${index + 1}`);
            const fieldId = `assist-answer-${id}`;
            const wrapper = document.createElement('div');

            const label = document.createElement('label');
            label.htmlFor = fieldId;
            label.className = 'mb-1 block text-sm font-medium text-dark dark:text-white';
            label.dir = 'auto';
            label.textContent = String(question?.label ?? '');
            wrapper.append(label);

            let field;

            if (question?.kind === 'choice') {
                field = document.createElement('select');
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = '— Choisir —';
                field.append(placeholder);

                (Array.isArray(question.options) ? question.options : []).slice(0, 5).forEach((option) => {
                    const optionElement = document.createElement('option');
                    optionElement.value = String(option);
                    optionElement.textContent = String(option);
                    field.append(optionElement);
                });
            } else {
                field = document.createElement('input');
                field.type = 'text';
                field.maxLength = 300;
                field.autocomplete = 'off';
            }

            field.id = fieldId;
            field.dir = 'auto';
            field.dataset.assistAnswer = id;
            field.className = FIELD_CLASS;
            field.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    composeDescription();
                }
            });

            wrapper.append(field);
            questionsBox.append(wrapper);
        });
    };

    const askQuestions = async () => {
        if (busy || description.value.trim().length < MIN_DESCRIPTION_LENGTH) {
            return;
        }

        panel.hidden = false;
        startButton.setAttribute('aria-expanded', 'true');
        resetPanel();
        actions.hidden = true;
        setBusy(true);
        setStatus('Préparation des questions…');

        try {
            const data = await postJson(root.dataset.questionsUrl, {
                title: title?.value ?? '',
                description: description.value,
                type: type?.value || null,
            });

            if (data?.sufficient === true || !Array.isArray(data?.questions) || data.questions.length === 0) {
                setSource(data?.source === 'local' ? 'Mode simplifié : analyse locale, sans IA.' : 'Analyse automatique.');
                setStatus('Votre description semble suffisamment précise. Vous pouvez l’envoyer telle quelle.', 'success');
                cancelButton?.focus();
                return;
            }

            renderQuestions(data.questions);
            actions.hidden = false;
            setSource(
                data.source === 'local'
                    ? 'Mode simplifié : questions génériques, sans IA.'
                    : 'Questions générées automatiquement, vérifiez-les.',
            );
            setStatus('Répondez à ces questions, puis demandez une description enrichie.');
            questionsBox.querySelector('input, select')?.focus();
        } catch (error) {
            if (error.name !== 'AbortError') {
                setStatus(error.message, 'error');
            }
        } finally {
            setBusy(false);
        }
    };

    const composeDescription = async () => {
        if (busy || questions.length === 0) {
            return;
        }

        const answers = {};

        questionsBox.querySelectorAll('[data-assist-answer]').forEach((field) => {
            const value = field.value.trim();

            if (value !== '') {
                answers[field.dataset.assistAnswer] = value.slice(0, 300);
            }
        });

        if (Object.keys(answers).length === 0) {
            setStatus('Répondez à au moins une question.', 'error');
            questionsBox.querySelector('input, select')?.focus();
            return;
        }

        const descriptionSent = description.value;
        preview.hidden = true;
        setBusy(true);
        setStatus('Rédaction de la description…');

        try {
            const data = await postJson(root.dataset.composeUrl, {
                title: title?.value ?? '',
                description: descriptionSent,
                type: type?.value || null,
                answers,
            });

            descriptionAtCompose = descriptionSent;
            previewText.value = String(data?.description ?? '');
            preview.hidden = false;
            modeNote.hidden = data?.source !== 'local';
            setStatus('Description proposée. Vérifiez-la avant de l’utiliser.', 'success');
            previewText.focus();
        } catch (error) {
            if (error.name !== 'AbortError') {
                setStatus(error.message, 'error');
            }
        } finally {
            setBusy(false);
        }
    };

    const closePanel = () => {
        controller?.abort();
        controller = null;
        panel.hidden = true;
        startButton.setAttribute('aria-expanded', 'false');
        resetPanel();
        setBusy(false);
    };

    const useProposedText = () => {
        const proposed = previewText.value.trim();

        if (proposed === '') {
            return;
        }

        if (
            description.value !== descriptionAtCompose
            && !window.confirm(
                'La description a été modifiée pendant l’aide. La remplacer par la description proposée ?',
            )
        ) {
            return;
        }

        description.value = proposed;
        description.dispatchEvent(new Event('input', { bubbles: true }));
        description.focus();
        closePanel();
    };

    description.addEventListener('input', updateStartButton);
    startButton.addEventListener('click', askQuestions);
    composeButton.addEventListener('click', composeDescription);
    cancelButton?.addEventListener('click', () => {
        closePanel();
        startButton.focus();
    });
    keepButton?.addEventListener('click', () => {
        closePanel();
        description.focus();
    });
    useButton?.addEventListener('click', useProposedText);

    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            closePanel();
            startButton.focus();
        }
    });

    updateStartButton();
};

document.querySelectorAll('[data-incident-assist]').forEach(initIncidentAssist);
