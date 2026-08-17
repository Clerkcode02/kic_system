import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Link } from 'react-router-dom'
import { Button, Card, Input } from '@/components'
import { withNext } from '@/lib/navigation/nextParam'
import type { WizardContact } from '@/stores/bookingWizardStore'

const contactSchema = z.object({
  name: z.string().trim().min(1, 'Enter your name').max(120, 'That name is too long'),
  email: z.string().trim().email('Enter a valid email address').max(255),
  phone: z
    .string()
    .trim()
    .min(7, 'Enter a phone number the provider can reach you on')
    .max(32, 'That phone number is too long'),
})

type ContactFormValues = z.infer<typeof contactSchema>

interface ContactStepProps {
  contact: WizardContact
  onChange: (contact: WizardContact) => void
  onNext: () => void
  onBack: () => void
  /** Where to return after a sign-in detour, so progress isn't abandoned. */
  returnTo: string
}

/**
 * Shown to anonymous visitors only — an authenticated user skips this step
 * entirely (the wizard doesn't render it), because their contact details
 * come from their account and sending guest fields as a logged-in user is a
 * 422 by design (SRS §6.1).
 *
 * The email collected here is the one that matters later: it's what the
 * tracking link is sent to, and what a future account must verify in order
 * to claim this booking.
 */
export function ContactStep({ contact, onChange, onNext, onBack, returnTo }: ContactStepProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ContactFormValues>({
    resolver: zodResolver(contactSchema),
    defaultValues: contact,
  })

  const onSubmit = (values: ContactFormValues) => {
    onChange(values)
    onNext()
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
      <div>
        <h2 className="text-base font-semibold text-gray-900">Your contact details</h2>
        <p className="mt-1 text-sm text-gray-600">
          No account needed. We&apos;ll email you a private link to track this booking, accept a
          quote and pay.
        </p>
      </div>

      <Input
        label="Full name"
        autoComplete="name"
        error={errors.name?.message}
        {...register('name')}
      />
      <Input
        label="Email"
        type="email"
        autoComplete="email"
        error={errors.email?.message}
        {...register('email')}
      />
      <Input
        label="Phone"
        type="tel"
        autoComplete="tel"
        error={errors.phone?.message}
        {...register('phone')}
      />

      <Card className="bg-gray-50 text-sm text-gray-600">
        Already have an account?{' '}
        <Link
          to={withNext('/login', returnTo)}
          className="rounded font-medium text-blue-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        >
          Sign in
        </Link>{' '}
        — your progress here is saved.
      </Card>

      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button type="submit" className="flex-1">
          Continue
        </Button>
      </div>
    </form>
  )
}
