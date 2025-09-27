import { createFormHook } from '@tanstack/react-form'
import { lazy } from 'react'

import { FormActions } from '@/components/form/form-actions'
import { fieldContext, formContext } from '@/context/form-context'

const CheckboxField = lazy(() =>
  import('@/components/form/fields/checkbox-field').then((m) => ({ default: m.CheckboxField }))
)
const ColorField = lazy(() => import('@/components/form/fields/color-field').then((m) => ({ default: m.ColorField })))
const Header = lazy(() => import('@/components/form/fields/display-fields').then((m) => ({ default: m.Header })))
const HtmlRenderer = lazy(() =>
  import('@/components/form/fields/display-fields').then((m) => ({ default: m.HtmlRenderer }))
)
const Notice = lazy(() => import('@/components/form/fields/display-fields').then((m) => ({ default: m.Notice })))
const ImageField = lazy(() => import('@/components/form/fields/image-field').then((m) => ({ default: m.ImageField })))
const MultiselectField = lazy(() =>
  import('@/components/form/fields/multiselect-field').then((m) => ({ default: m.MultiselectField }))
)
const NumberField = lazy(() =>
  import('@/components/form/fields/number-field').then((m) => ({ default: m.NumberField }))
)
const RepeaterField = lazy(() =>
  import('@/components/form/fields/repeater-field').then((m) => ({ default: m.RepeaterField }))
)
const SelectField = lazy(() =>
  import('@/components/form/fields/select-field').then((m) => ({ default: m.SelectField }))
)
const TelField = lazy(() => import('@/components/form/fields/tel-field').then((m) => ({ default: m.TelField })))
const TextField = lazy(() => import('@/components/form/fields/text-field').then((m) => ({ default: m.TextField })))
const TextareaField = lazy(() =>
  import('@/components/form/fields/textarea-field').then((m) => ({ default: m.TextareaField }))
)

export const { useAppForm, withForm, withFieldGroup } = createFormHook({
  fieldComponents: {
    CheckboxField,
    ColorField,
    ImageField,
    MultiselectField,
    NumberField,
    RepeaterField,
    SelectField,
    TelField,
    TextField,
    TextareaField,
    HtmlRenderer,
    Header,
    Notice,
  },
  formComponents: {
    FormActions,
  },
  fieldContext,
  formContext,
})
