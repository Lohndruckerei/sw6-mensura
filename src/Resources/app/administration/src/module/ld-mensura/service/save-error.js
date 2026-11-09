/**
 * Lesbare Fehlermeldung beim Speichern einer Detailseite:
 * - doppelter technischer Name → eigener Hinweis
 * - 403 / fehlendes Recht → „Keine Berechtigung“ statt „Pflichtfelder ungültig“
 * - sonst der erste Fehlertext der API, nur ohne Text die Shopware-Standardmeldung
 */
export default function saveErrorMessage(vm, exception, duplicateMarker = 'technical_name') {
    const errors = exception?.response?.data?.errors ?? [];
    const detail = JSON.stringify(errors);

    if (detail.includes(duplicateMarker)) {
        return vm.$tc('ld-mensura.general.errorDuplicateTechnicalName');
    }
    if (exception?.response?.status === 403 || detail.includes('MISSING_PRIVILEGE')) {
        return vm.$tc('ld-mensura.general.errorForbidden');
    }

    const first = errors.find((error) => typeof error?.detail === 'string' && error.detail !== '');
    return first?.detail ?? vm.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid');
}
