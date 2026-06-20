import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { createOrganization } from '../api/organizations'
import { CreateOrgError } from '../schemas/organizations'

/**
 * Create-organization onboarding (Story 1.1, AC-1/2/4). Non-skippable: there is
 * no skip/dismiss/back control — the no-org gate forces this route until an org
 * exists. On success the orgs query is invalidated so the gate lands on Home.
 */
export function OnboardingPage() {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')

  const mutation = useMutation({
    mutationFn: (orgName: string) => createOrganization(orgName),
    onSuccess: () => {
      // Re-evaluate the gate: user now has an org → operator Home.
      return queryClient.invalidateQueries({ queryKey: ['organizations'] })
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    const trimmed = name.trim()
    if (trimmed.length === 0) return
    mutation.mutate(trimmed)
  }

  return (
    <section aria-labelledby="onboarding-heading">
      <h1 id="onboarding-heading">Create your organization</h1>
      <p>Name your organization to set up your tenant workspace. You become its admin.</p>

      {renderError(mutation.error)}

      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="Organization name"
            name="organization-name"
            required
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
        </FormLayout>
        <Button
          type="submit"
          loading={mutation.isPending}
          disabled={name.trim().length === 0}
        >
          Create organization
        </Button>
      </form>
    </section>
  )
}

function renderError(error: unknown) {
  if (!error) return null
  if (error instanceof CreateOrgError) {
    if (error.code === 'DUPLICATE_NAME') {
      return (
        <Banner variant="error">
          {error.message || 'An organization with a similar name already exists.'}
        </Banner>
      )
    }
    if (error.code === 'UNAUTHENTICATED') {
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    }
  }
  return (
    <Banner variant="error">
      We could not create your organization. Please try again.
    </Banner>
  )
}
