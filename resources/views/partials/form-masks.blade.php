<script>
    (() => {
        if (window.__fechouFormMasksLoaded) {
            return;
        }

        window.__fechouFormMasksLoaded = true;

        const digits = (value, max = null) => {
            let result = String(value ?? '')
                .replace(/\D/g, '');

            if (max !== null) {
                result = result.slice(0, max);
            }

            return result;
        };

        const documentCharacters = (value) => {
            return String(value ?? '')
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '')
                .slice(0, 14);
        };

        const documentMask = (value) => {
            const characters =
                documentCharacters(value);

            if (
                /^\d{0,11}$/.test(
                    characters
                )
            ) {
                return characters
                    .replace(
                        /^(\d{3})(\d)/,
                        '$1.$2'
                    )
                    .replace(
                        /^(\d{3})\.(\d{3})(\d)/,
                        '$1.$2.$3'
                    )
                    .replace(
                        /\.(\d{3})(\d)/,
                        '.$1-$2'
                    );
            }

            return characters
                .replace(
                    /^([A-Z0-9]{2})([A-Z0-9])/,
                    '$1.$2'
                )
                .replace(
                    /^([A-Z0-9]{2})\.([A-Z0-9]{3})([A-Z0-9])/,
                    '$1.$2.$3'
                )
                .replace(
                    /\.([A-Z0-9]{3})([A-Z0-9])/,
                    '.$1/$2'
                )
                .replace(
                    /([A-Z0-9]{4})([A-Z0-9])/,
                    '$1-$2'
                );
        };

        const phoneMask = (value) => {
            let valueDigits = digits(value);

            if (
                valueDigits.length > 11
                && valueDigits.startsWith('55')
            ) {
                valueDigits = valueDigits.slice(2);
            }

            valueDigits = valueDigits.slice(0, 11);

            if (valueDigits.length <= 10) {
                return valueDigits
                    .replace(/^(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d)/, '$1-$2');
            }

            return valueDigits
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d)/, '$1-$2');
        };

        const cepMask = (value) => {
            return digits(value, 8)
                .replace(/^(\d{5})(\d)/, '$1-$2');
        };

        const ufMask = (value) => {
            return String(value ?? '')
                .replace(/[^A-Za-z]/g, '')
                .toUpperCase()
                .slice(0, 2);
        };

        const masks = {
            document: documentMask,
            phone: phoneMask,
            cep: cepMask,
            uf: ufMask,
        };

        const applyMask = (element) => {
            const name =
                element.dataset.fechouMask;

            const mask = masks[name];

            if (!mask) {
                return;
            }

            const masked =
                mask(element.value);

            if (element.value !== masked) {
                element.value = masked;
            }
        };

        const applyAll = (root = document) => {
            if (
                root instanceof Element
                && root.matches('[data-fechou-mask]')
            ) {
                applyMask(root);
            }

            root.querySelectorAll?.(
                '[data-fechou-mask]'
            ).forEach(applyMask);
        };

        document.addEventListener(
            'input',
            (event) => {
                const element =
                    event.target.closest?.(
                        '[data-fechou-mask]'
                    );

                if (element) {
                    applyMask(element);
                }
            },
            true
        );

        const observer =
            new MutationObserver(
                (mutations) => {
                    for (const mutation of mutations) {
                        for (
                            const node
                            of mutation.addedNodes
                        ) {
                            if (
                                node instanceof Element
                            ) {
                                applyAll(node);
                            }
                        }
                    }
                }
            );

        observer.observe(
            document.documentElement,
            {
                childList: true,
                subtree: true,
            }
        );

        queueMicrotask(
            () => applyAll(document)
        );
    })();
</script>
